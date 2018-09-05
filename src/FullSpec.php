<?php
    namespace Enobrev\API;

    use Enobrev\API\FullSpec\ComponentInterface;
    use Enobrev\API\FullSpec\ComponentListInterface;
    use Enobrev\API\FullSpec\Component;
    use Enobrev\API\Spec\JsonResponse;
    use Enobrev\API\Spec\ProcessErrorResponse;
    use Enobrev\API\Spec\ServerErrorResponse;
    use Enobrev\API\Spec\ValidationErrorResponse;
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

        const _ANY                          = '_any';
        const _DEFAULT                      = '_default';
        const _CREATED                      = 'Created';
        const _BAD_REQUEST                  = 'BadRequest';
        const _UNAUTHORIZED                 = 'Unauthorized';
        const _FORBIDDEN                    = 'Forbidden';
        const _UNPROCESSABLE_ENTITY         = 'UnprocessableEntiry';
        const _SERVER_ERROR                 = 'ServerError';
        const _MULTI_STATUS                 = 'MultiStatus';

        const SCHEMA_ANY                    = 'schemas/' . self::_ANY;
        const SCHEMA_DEFAULT                = 'schemas/' . self::_DEFAULT;

        const RESPONSE_DEFAULT              = 'responses/' . self::_DEFAULT;
        const RESPONSE_CREATED              = 'responses/' . self::_CREATED;
        const RESPONSE_BAD_REQUEST          = 'responses/' . self::_BAD_REQUEST;
        const RESPONSE_UNAUTHORIZED         = 'responses/' . self::_UNAUTHORIZED;
        const RESPONSE_FORBIDDEN            = 'responses/' . self::_FORBIDDEN;
        const RESPONSE_UNPROCESSABLE_ENTITY = 'responses/' . self::_UNPROCESSABLE_ENTITY;
        const RESPONSE_SERVER_ERROR         = 'responses/' . self::_SERVER_ERROR;
        const RESPONSE_MULTI_STATUS         = 'responses/' . self::_MULTI_STATUS;

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

        /** @var OpenApiInterface[] */
        private $aComponents;

        /** @var Spec[] */
        private $aPaths;

        /** @var Spec[] */
        private $aSpecs;

        public function __construct() {
            $this->aComponents      = [];
            $this->aSpecs           = [];
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
            $sPath       = $oSpec->getPath();
            $sHttpMethod = $oSpec->getHttpMethod();
            if (!isset($this->aPaths[$sPath])) {
                $this->aPaths[$sPath] = [];
            }

            $this->aPaths[$sPath][$sHttpMethod] = $oSpec;

            // TODO: Generate Components from Spec as well
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
            ksort($this->aSpecs, SORT_NATURAL);
            return $this->aSpecs;
        }

        /**
         * @throws Exception\Response
         * @throws ReflectionException
         */
        protected function generateData() {
            //$this->tablesFromFile();
            //$this->specsFromSQLFile();
            //$this->specsFromClasses();
            $this->specsFromSpecInterfaces();
        }

        /**
         * @param Component\Reference $oReference
         * @return Component\Response
         */
        public function getComponent(Component\Reference $oReference) {
            return $this->aComponents[$oReference->getName()];
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
                    'schemas' => self::DEFAULT_RESPONSE_SCHEMAS,
                    'responses' => [
                        self::_DEFAULT => Component\Response::create(self::RESPONSE_DEFAULT)
                            ->description('Default Response')
                            ->json(Component\Reference::create(FullSpec::SCHEMA_DEFAULT))
                            ->getOpenAPI(),
                        self::_CREATED => Component\Response::create(self::RESPONSE_CREATED)->json(Component\Reference::create(FullSpec::SCHEMA_DEFAULT))
                            ->description('New record was created.  If a new key was generated for the record, See Location header')
                            ->getOpenAPI(),
                        self::_BAD_REQUEST => Component\Response::create(self::RESPONSE_DEFAULT)
                            ->description('Request Validation Error.  See `_errors.validation` in the response for more information')
                            ->json(
                                ValidationErrorResponse::create()
                                    ->message('Request Validation Error.  See `_errors.validation` in the response for more information')
                                    ->code(HTTP\BAD_REQUEST)
                            )
                            ->getOpenAPI(),
                        self::_UNAUTHORIZED => Component\Response::create(self::RESPONSE_DEFAULT)
                            ->description('Access Token Invalid.  Client should Re-authenticate')
                            ->json(
                                ProcessErrorResponse::create()
                                    ->message('Unauthorized')
                                    ->code(HTTP\UNAUTHORIZED)
                            )
                            ->getOpenAPI(),
                        self::_FORBIDDEN => Component\Response::create(self::RESPONSE_DEFAULT)
                            ->description('Access Denied.  Authenticated Profile does not have access.')
                            ->json(
                                ProcessErrorResponse::create()
                                    ->message('Forbidden')
                                    ->code(HTTP\FORBIDDEN)
                            )
                            ->getOpenAPI(),
                        self::_UNPROCESSABLE_ENTITY => Component\Response::create(self::RESPONSE_DEFAULT)
                            ->description('Request was Valid and Server handled properly, but something else went wrong.  See `_errors.process` in the response for more infomration.')
                            ->json(
                                ProcessErrorResponse::create()
                                    ->message('Request was Valid and Server handled properly, but something else went wrong.  See `_errors.validation` in the response for more infomration.')
                                    ->code(HTTP\BAD_REQUEST)
                            )
                            ->getOpenAPI(),
                        self::_SERVER_ERROR => Component\Response::create(self::RESPONSE_DEFAULT)
                            ->description('Something went wrong on the server.  Contact an API developer for info.  `_errors.process` will have details, and `_request.logs` will have references for the developers to find what happened.')
                            ->json(
                                ServerErrorResponse::create()
                                    ->message('Something went wrong on the server.  Contact an API developer for info.  `_errors.server` will have details, and `_request.logs` will have references for the developers to find what happened.')
                                    ->code(HTTP\INTERNAL_SERVER_ERROR)
                            )
                            ->getOpenAPI(),
                        self::_MULTI_STATUS => Component\Response::create(self::RESPONSE_DEFAULT)
                            ->json(
                                JsonResponse::create()->allOf([
                                    Component\Reference::create(FullSpec::SCHEMA_DEFAULT),
                                    [
                                        '_request.multiquery' => Component\Reference::create(FullSpec::RESPONSE_DEFAULT)->getOpenAPI()

                                    ]
                                ])
                            )
                            ->description('The overall multi-endpoint query was successful, but some endpoints were not.  See `_request.multiquery` in the response for more information')
                            ->getOpenAPI()
                    ]
                ]
            ]);

            $oData->set('components.schemas._any', (object) []);

            $oData->mergeRecursiveDistinct("components.responses", $this->aResponses);
            $oData->mergeRecursiveDistinct("components.schemas", $this->aSchemas);

            ksort($this->aComponents, SORT_NATURAL); // because why not
            foreach($this->aComponents as $oComponent) {
                $sName = str_replace('/', '.', $oComponent->getName());
                $oData->set("components.$sName", $oComponent->getOpenAPI());
            }

            /**
             * @var string $sPath
             * @var Spec $oSpec
             */
            ksort($this->aSpecs, SORT_NATURAL); // ensures named sub-paths come before {var} subpaths
            foreach($this->aSpecs as $sPath => $aMethods) {
                foreach($aMethods as $sHttpMethod => $sSpecInterface) {
                    /** @var SpecInterface $oSpecInterface */
                    $oSpecInterface = new $sSpecInterface;
                    $oSpec          = $oSpecInterface->spec();

                    if (count($aScopes)) {
                        if (!$oSpec->hasAnyOfTheseScopes($aScopes)) {
                            continue;
                        }
                    }
                    $oData->set("paths.{$oSpec->getPath()}.{$oSpec->getLowerHttpMethod()}", $oSpec->generateOpenAPI());
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
         * @deprecated
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

                $this->schemas("table-$sTable", Spec::toJsonSchema(Spec::tableToParams($oTable)));
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
         * @deprecated
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
         * @deprecated
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
        /**
         * @throws ReflectionException
         */
        private function specsFromSpecInterfaces() {
            foreach (self::$aVersions as $sVersion) {
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
                        if (preg_match('/namespace\s([^;]+)/', (string) $sContents, $aMatchesNamespace)) {
                            if (preg_match_all('/class\s+([^\s]+)/', (string) $sContents, $aMatchesClass)) {
                                foreach($aMatchesClass[1] as $sClass) {
                                    if (strpos($sClass, 'Exception')) {
                                        continue;
                                    }

                                    $sNamespace = $aMatchesNamespace[1];
                                    $sFullClass = implode('\\', [$sNamespace, $sClass]);

                                    $oReflectionClass = new ReflectionClass($sFullClass);

                                    if ($oReflectionClass->implementsInterface(SpecInterface::class)) {
                                        /** @var SpecInterface $oClass */
                                        $oClass      = new $sFullClass();
                                        $oSpec       = $oClass->spec();
                                        $sPath       = $oSpec->getPath();
                                        $sHttpMethod = $oSpec->getHttpMethod();

                                        if (!isset($this->aSpecs[$sPath])) {
                                            $this->aSpecs[$sPath] = [];
                                        }

                                        $this->aSpecs[$sPath][$sHttpMethod] = $sFullClass;
                                    } else if ($oReflectionClass->implementsInterface(ComponentListInterface::class)) {
                                        /** @var ComponentListInterface $oClass */
                                        $oClass      = new $sFullClass();
                                        $aComponents = $oClass->components();
                                        foreach($aComponents as $oComponent) {
                                            $this->aComponents[$oComponent->getName()] = $oComponent;
                                        }
                                    }
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
                    "method"        => [
                        "type" => "string",
                        "enum" => ["GET", "POST", "PUT", "DELETE"]
                    ],
                    "path"          => ["type" => "string"],
                    "params"         => [
                        "type" => "object",
                        "properties" => [
                            "path" => [
                                "type" => "object",
                                "description" => "Parameters that were found in the URI Path"
                            ],
                            "query" => [
                                "type" => "object",
                                "description" => "Parameters that were found in the URI Search"
                            ],
                            "post" => [
                                "type" => "object",
                                "description" => "Parameters that were found in the Request Body"
                            ]
                        ]
                    ],
                    "headers"   => [
                        "type" => "string",
                        "description" => "JSON Encoded String of request headers"
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
                    "status" => ["type" => "integer"]
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
                            ]
                        ]
                    ]
                ],
                "additionalProperties"=> false
            ]
        ];
    }
