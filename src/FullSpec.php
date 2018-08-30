<?php
    namespace Enobrev\API;

    use function Enobrev\dbg;
    use RecursiveIteratorIterator;
    use RecursiveDirectoryIterator;
    use FilesystemIterator;
    use ReflectionClass;
    use ReflectionException;

    use Adbar\Dot;
    use Enobrev\ORM\Table;
    use Zend\Diactoros\ServerRequest;

    class FullSpec {
        /** @var string */
        private static $sPathToSpec;

        /** @var string */
        private static $sAppNamespace;

        /** @var string */
        private static $sPathToSQLJson;

        /** @var string */
        private static $sPathToAPIClasses;

        /** @var array */
        private static $aDatabaseSchema;

        /** @var array */
        private static $aVersions;

        /** @var array */
        private $aSchemas;

        /** @var array */
        private $aResponses;

        /** @var Spec[] */
        private $aPaths;

        public function __construct() {
            $this->aPaths           = [];
            $this->aResponses       = [];
            $this->aSchemas         = [];
        }

        /**
         * @param string $sPathToSpec
         * @param string $sPathToSQLJson
         * @param string $sAppNamespace
         * @param string $sPathToAPIClasses
         * @param array $aVersions
         * @throws Exception
         */
        public static function init(string $sPathToSpec, string $sPathToSQLJson, string $sAppNamespace, string $sPathToAPIClasses, array $aVersions): void {
            self::$sPathToSpec          = $sPathToSpec;
            self::$sPathToSQLJson       = $sPathToSQLJson;
            self::$sAppNamespace        = $sAppNamespace;
            self::$sPathToAPIClasses    = $sPathToAPIClasses;
            self::$aVersions            = $aVersions;

            if (!file_exists($sPathToSQLJson)) {
                throw new Exception('Missing SQL JSON file');
            }
        }

        public function schemas($sSchema, $aSchema) {
            $this->aSchemas[$sSchema] = $aSchema;
        }

        /**
         * @param string $sName
         * @param string $sDescription
         * @param array $aContent
         */
        public function responses(string $sName, string $sDescription, array $aContent) {
            $this->aResponses[$sName] = [
                'description' => $sDescription,
                'content'     => $aContent
            ];
        }

        public function defaultSchemaResponse(string $sResponse) {
            $this->responses($sResponse, "A successful response object with the $sResponse data and the standard metadata", [
                'application/json' => [
                    'schema' => [
                        'allOf' => [
                            ['$ref' => "#/components/schemas/_default"],
                            ['$ref' => "#/components/schemas/$sResponse"],
                        ]
                    ]
                ]
            ]);
        }

        public function paths(Spec $oSpec) {
            if (!isset($this->aPaths[$oSpec->Path])) {
                $this->aPaths[$oSpec->Path] = [];
            }

            $this->aPaths[$oSpec->Path][$oSpec->HttpMethod] = $oSpec;
        }

        public function getPath(string $sPath, string $sHttpMethod): ?Spec {
            return $this->aPaths[$sPath][$sHttpMethod] ?? null;
        }

        public function removePath(string $sPath, string $sHttpMethod): void {
            if (isset($this->aPaths[$sPath])) {
                if (isset($this->aPaths[$sPath][$sHttpMethod])) {
                    unset($this->aPaths[$sPath][$sHttpMethod]);
                }
            }

            if (count($this->aPaths[$sPath]) == 0) {
                unset($this->aPaths[$sPath]);
            }
        }

        public function removeSpec(Spec $oSpec) {
            $this->removePath($oSpec->Path, $oSpec->HttpMethod);
        }

        /**
         * @throws Exception\Response
         * @throws ReflectionException
         */
        public static function generateAndCache() {
            $oFullSpec = new self;
            $oFullSpec->generateData();
            file_put_contents(self::$sPathToSpec, serialize($oFullSpec));
        }

        /**
         * @return self
         * @throws Exception\Response
         * @throws ReflectionException
         */
        public static function getFromCache() {
            if (!file_exists(self::$sPathToSpec)) {
                self::generateAndCache();
            }

            return unserialize(file_get_contents(self::$sPathToSpec));
        }

        public static function generateLiveForDevelopment() {
            $oFullSpec = new self;
            $oFullSpec->generateData();
            return $oFullSpec;
        }

        public function getRoutes() {
            $aRoutes = [];
            foreach($this->aPaths as $sPath => $aHttpMethods) {
                $aRoutes[$sPath] = array_keys($aHttpMethods);
            }

            return $aRoutes;
        }

        /**
         * @throws Exception\Response
         * @throws ReflectionException
         */
        protected function generateData() {
            $this->tablesFromFile();
            $this->specsFromSQLFile();
            $this->specsFromClasses();
        }

        /**
         * Generates paths and components for openapi spec.  Final spec still requires info and servers stanzas
         * @param array $aScopes
         * @return Dot
         * @throws Exception\Response
         * @throws ReflectionException
         */
        public function getOpenAPI(array $aScopes = []) {
            $oData = new Dot([
                'openapi'   => '3.0.1',
                'info'      => [],
                'servers'   => [],
                'paths'     => [],
                'components' => [
                    'schemas' => self::DEFAULT_RESPONSE_SCHEMAS
                ]
            ]);

            $oData->set('components.schemas._any', (object) []);

            $oData->mergeRecursiveDistinct("components.responses", $this->aResponses);
            $oData->mergeRecursiveDistinct("components.schemas", $this->aSchemas);

            /**
             * @var string $sPath
             * @var Spec $oSpec
             */

            foreach($this->aPaths as $sPath => $aMethods) {
                foreach($aMethods as $sHttpMethod => $oSpec) {
                    if (count($aScopes)) {
                        if (count($oSpec->Scopes) && count(array_intersect($aScopes, $oSpec->Scopes))) {
                            $sMethod = strtolower($oSpec->HttpMethod);
                            $oData->set("paths.{$oSpec->Path}.{$sMethod}", $oSpec->generateOpenAPI());
                        }
                    } else {
                        $sMethod = strtolower($oSpec->HttpMethod);
                        $oData->set("paths.{$oSpec->Path}.{$sMethod}", $oSpec->generateOpenAPI());
                    }
                }
            }

            return $oData;
        }

        /**
         * @return array
         */
        private static function getDatabaseSchema() {
            if (!self::$aDatabaseSchema) {
                self::$aDatabaseSchema = json_decode(file_get_contents(self::$sPathToSQLJson), true);
            }

            return self::$aDatabaseSchema;
        }

        /**
         * @param string $sTableClass
         * @return string
         * @throws Exception\Response
         */
        private function _getNamespacedTableClassName(string $sTableClass): string {
            if (self::$sAppNamespace === null) {
                throw new Exception\Response('FullSpec Not Initialized');
            }

            return implode('\\', [self::$sAppNamespace, $sTableClass]);
        }

        /**
         * @throws Exception\Response
         */
        private function tablesFromFile() {
            $aDatabase  = self::getDatabaseSchema();

            foreach($aDatabase['tables'] as $sTable => $aTable) {
                if (!DataMap::hasClassPath($sTable)) {
                    continue;
                }

                /** @var Table $oTable */
                $sClass     = $this->_getNamespacedTableClassName($aTable['table']['class']);
                $oTable     = new $sClass;
                if ($oTable instanceof Table === false) {
                    continue;
                }

                $sId = 'id';
                $aPrimary = $oTable->getPrimary();
                if (count($aPrimary) === 1) {
                    $sId = DataMap::getPublicName($oTable, $aPrimary[0]->sColumn) ?? 'id';
                }

                $sTemplatedId = "{{$sId}}";

                $this->schemas("table-$sTable", Spec::paramsToJsonSchema(Spec::tableToParams($oTable)));
                $this->schemas("collection-$sTable", [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        $sTable => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                $sTemplatedId => ['$ref' => "#/components/schemas/table-$sTable"]
                            ]
                        ]
                    ]
                ]);

                $this->responses($sTable, "A successful response object with a collection of $sTable and standard metadata", [
                    'application/json' => [
                        'schema' => [
                            'allOf' => [
                                ['$ref' => "#/components/schemas/_default"],
                                ['$ref' => "#/components/schemas/collection-$sTable"]
                            ]
                        ]
                    ]
                ]);
            }
        }

        /**
         * @throws Exception\Response
         */
        private function specsFromSQLFile() {
            $aDatabase  = self::getDatabaseSchema();

            foreach($aDatabase['tables'] as $sTable => $aTable) {
                if (!DataMap::hasClassPath($sTable)) {
                    continue;
                }

                $sClass     = $this->_getNamespacedTableClassName($aTable['table']['class']);
                $oTable     = new $sClass;
                if ($oTable instanceof Table === false) {
                    continue;
                }

                /** @var Rest $oRest */
                $oRest = Route::_getRestClass(new Request(new ServerRequest));
                $oRest->setBaseTable($sClass);
                $oRest->spec($this);
            }
        }

        /**
         * @throws ReflectionException
         */
        private function specsFromClasses() {
            foreach (self::$aVersions as $sVersion) {
                // TODO: Collect all paths from previous version that are not now private / protected, and copy them to the current version
                // TODO: for instance, if we have v1/class/methoda, and v2 doesn't override it, then we should have v2/class/methoda which points to v1/class/methoda

                $sVersionPath = self::$sPathToAPIClasses . '/' . $sVersion . '/';
                if (file_exists($sVersionPath)) {
                    /** @var \SplFileInfo[] $aFiles */
                    $aFiles  = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator(
                            $sVersionPath,
                            FilesystemIterator::SKIP_DOTS
                        )
                    );

                    foreach($aFiles as $oFile) {
                        $sContents = file_get_contents($oFile->getPathname());
                        if (preg_match('/class\s+([^\s]+)/', (string) $sContents, $aMatchesClass)) {
                            $sClass = $aMatchesClass[1];

                            if (preg_match('/namespace\s([^;]+)/', (string) $sContents, $aMatchesNamespace)) {
                                $sNamespace = $aMatchesNamespace[1];
                                $sFullClass = implode('\\', [$sNamespace, $sClass]);

                                $oReflectionClass = new ReflectionClass($sFullClass);

                                if ($oReflectionClass->implementsInterface(RestfulInterface::class)
                                &&  $oReflectionClass->hasMethod('spec')
                                &&  $oReflectionClass->getMethod('spec')->class == $oReflectionClass->name) {
                                    //dbg('Restful', $sFullClass);
                                    /** @var RestfulInterface $oClass */
                                    $oClass = new $sFullClass(new Request(new ServerRequest));
                                    $oClass->spec($this);
                                } else if ($oReflectionClass->isSubclassOf(Base::class)
                                       &&  $oReflectionClass->hasMethod('spec')
                                       &&  $oReflectionClass->getMethod('spec')->class == $oReflectionClass->name) {
                                    //dbg('Base', $sFullClass);
                                    /** @var Base $oClass */
                                    $oClass = new $sFullClass(new Request(new ServerRequest));
                                    $oClass->spec($this);
                                }
                            }
                        }
                    }
                }
            }
        }

        const DEFAULT_RESPONSE_SCHEMAS = [
            "_default" => [
                "type" => "object",
                "properties" => [
                    "_server" => [
                        '$ref' => "#/components/schemas/_server"
                    ],
                    "_request" => [
                        '$ref' => "#/components/schemas/_request"
                    ]
                ]
            ],
            "_server" => [
                "type" => "object",
                "properties"=> [
                    "timezone"      => ["type" => "string"],
                    "timezone_gmt"  => ["type" => "string"],
                    "date"          => ["type" => "string"],
                    "date_w3c"      => ["type" => "string"]
                ],
                "additionalProperties"=> false
            ],
            "_request" => [
                "type" => "object",
                "properties"=> [
                    "validation" => [
                        "type" => "object",
                        "properties" => [
                            "status" => [
                                "type" => "string",
                                "enum" => ["PASS", "FAIL"]
                            ],
                            "errors" => [
                                "type" => "array",
                                "items" => ['$ref' => "#/components/schemas/_validation_error"]
                            ]
                        ]
                    ],
                    "logs"      => [
                        "type" => "object",
                        "properties" => [
                            "thread" => [
                                "type" => "string",
                                "description" => "Alphanumeric hash for looking up entire request thread in logs"
                            ],
                            "request" => [
                                "type" => "string",
                                "description" => "Alphanumeric hash for looking up specific API request in logs"
                            ]
                        ]
                    ],
                    "method"        => [
                        "type" => "string",
                        "enum" => ["GET", "POST", "PUT", "DELETE"]
                    ],
                    "path"          => ["type" => "string"],
                    "attributes"    => [
                        "type" => "array",
                        "description" => "Parameters pulled from the path",
                        "items" => ["type" => "string"]
                    ],
                    "query"         => [
                        "type" => "array",
                        "items" => ["type" => "string"]
                    ],
                    "data"          => [
                        '$ref' => '#/components/schemas/_any',
                        "description" => "POSTed Data"
                    ]
                ],
                "additionalProperties"=> false
            ],
            "_response" => [
                "type" => "object",
                "properties"=> [
                    "validation" => [
                        "type" => "object",
                        "properties" => [
                            "status" => [
                                "type" => "string",
                                "enum" => ["PASS", "FAIL"]
                            ],
                            "errors" => [
                                "type" => "array",
                                "items" => ['$ref' => "#/components/schemas/_validation_error"]
                            ]
                        ]
                    ]
                ],
                "additionalProperties"=> false
            ],
            "_validation_error" => [
                "type" => "object",
                "properties" => [
                    "property"      => ["type" => "string"],
                    "pointer"       => ["type" => "string"],
                    "message"       => ["type" => "string"],
                    "constraint"    => ["type" => "string"],
                    "context"       => ["type" => "number"],
                    "minimum"       => ["type" => "number"],
                    "value"         => [
                        '$ref' => '#/components/schemas/_any'
                    ]
                ]
            ]
        ];
    }
