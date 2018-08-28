<?php
    namespace Enobrev\API;

    use Adbar\Dot;
    use Enobrev\ORM\Table;
    use Zend\Diactoros\ServerRequest;

    class FullSpec {
        /** @var string */
        private static $sAppNamespace;

        /** @var string */
        private static $sPathToSQLJson;

        /** @var array */
        private static $aDatabaseSchema;

        /** @var Dot */
        private $oData;

        /** @var array */
        private $aInfo;

        /** @var array */
        private $aSchemas;

        /** @var array */
        private $aResponses;

        /** @var Spec[] */
        private $aPaths;

        public function __construct( array $aInfo) {
            $this->aPaths           = [];
            $this->aResponses       = [];
            $this->aSchemas         = [];
            $this->aInfo            = $aInfo;
        }

        /**
         * @param string $sAppNamespace
         * @param string $sPathToSQLJson
         * @throws Exception
         */
        public static function init(string $sAppNamespace, string $sPathToSQLJson): void {
            self::$sAppNamespace  = $sAppNamespace;
            self::$sPathToSQLJson = $sPathToSQLJson;

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
            $this->aPaths["{$oSpec->HttpMethod}.{$oSpec->Path}"] = $oSpec;
        }

        public function getPath(string $sHttpMethod, string $sPath): ?Spec {
            return $this->aPaths["{$sHttpMethod}.{$sPath}"] ?? null;
        }

        /**
         * @param string $sAPIUrl
         * @param string $sAPIDescription
         * @param array $aScopes
         * @return Dot
         * @throws Exception\Response
         */
        public function generateOpenAPI(string $sAPIUrl, string $sAPIDescription, array $aScopes = []) {
            $this->tablesFromFile();
            $this->specsFromSQLFile();
            $this->specsFromRoutes();

            $oData = new Dot([
                'openapi' => '3.0.1',
                'info'    => $this->aInfo,
                'servers' => [
                    [
                        'url'         => $sAPIUrl,
                        'description' => $sAPIDescription
                    ]
                ],
                'paths'         => [],
                'components'   => [
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

            foreach($this->aPaths as $oSpec) {
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

            return $oData;
        }

        public function get($sPath) {
            return $this->oData->get($sPath);
        }

        public function set($sPath, $mValue) {
            return $this->oData->set($sPath, $mValue);
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

        private function specsFromRoutes() {
            $aRoutes = Route::_getCachedRoutes() + Route::_getCachedQueryRoutes();

            foreach($aRoutes as $aRoute) {
                $oClass  = null;
                $sClass  = $aRoute['class'];

                switch($aRoute['type']) {
                    case Route::CACHED_ROUTE_BASE:
                    case Route::QUERY_ROUTE_REST:
                    case Route::QUERY_ROUTE_ENDPOINT:
                        /** @var Base $oClass */
                        $oClass  = new $sClass(new Request(new ServerRequest));
                        break;

                    case Route::QUERY_ROUTE_TABLE:
                        // Not currently handled
                }

                if ($oClass) {
                    $oClass->spec($this);
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
