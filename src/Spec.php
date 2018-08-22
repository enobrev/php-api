<?php
    namespace Enobrev\API;

    use Adbar\Dot;
    use Enobrev\API\Exception\DocumentationException;
    use Enobrev\API\Exception\InvalidRequest;
    use function Enobrev\array_not_associative;
    use function Enobrev\dbg;
    use Enobrev\ORM\Table;
    use Enobrev\ORM\Field;
    use JsonSchema\Constraints\Constraint;
    use JsonSchema\Validator;

    class Spec {
        /** @var string */
        public $Summary;

        /** @var string */
        public $Description;

        /** @var boolean */
        public $RequestValidated = false;

        /** @var boolean */
        public $Deprecated;

        /** @var string */
        public $Path;

        /** @var string */
        public $HttpMethod;

        /** @var string */
        public $Method;

        /** @var string[] */
        public $Scopes;

        /** @var Param[] */
        public $PathParams = [];

        /** @var Param[] */
        public $QueryParams = [];

        /** @var array */
        public $ResponseSchema;

        /** @var string */
        public $ResponseReference;

        /** @var Param[] */
        public $InHeaders = [];

        /** @var Param[] */
        public $OutHeaders = [];

        /** @var array */
        public $CodeSamples = [];

        /** @var array */
        public $ResponseHeaders = [];

        /** @var array */
        public $Responses = [
            HTTP\OK => 'Success',
        ];

        /** @var string[] */
        public $Tags = [];

        /**
         * Spec constructor.
         * @param string $sHttpMethod
         * @param string $sPath
         */
        public function __construct(string $sHttpMethod, string $sPath) {
            $this->httpMethod($sHttpMethod);
            $this->path($sPath);
        }

        public function summary(string $sSummary):self {
            $this->Summary = $sSummary;
            return $this;
        }

        public function description(string $sDescription):self {
            $this->Description = $sDescription;
            return $this;
        }

        public function deprecated(?bool $bDeprecated = true):self {
            $this->Deprecated = $bDeprecated;
            return $this;
        }

        public function path(string $sPath):self {
            $this->Path = $sPath;
            return $this;
        }

        public function httpMethod(string $sHttpMethod):self {
            $this->HttpMethod = $sHttpMethod;
            return $this;
        }

        public function method(string $sMethod):self {
            $this->Method = $sMethod;
            return $this;
        }

        public function scopes(array $aScopes):self {
            $this->Scopes = $aScopes;
            return $this;
        }

        public function pathParams(array $aParams):self {
            $this->PathParams = $aParams;
            return $this;
        }

        public function queryParams(array $aParams):self {
            $this->QueryParams = $aParams;
            return $this;
        }

        public function responseHeader(string $sHeader, string $sValue):self {
            $this->ResponseHeaders[$sHeader] = $sValue;
            return $this;
        }

        public function removeResponse(int $iStatus):self {
            unset($this->Responses[$iStatus]);
            return $this;
        }

        public function response(int $iStatus, string $sDescription):self {
            $this->Responses[$iStatus] = $sDescription;
            return $this;
        }

        public function tags(array $aTags):self {
            $this->Tags += $aTags;
            $this->Tags = array_unique($aTags);
            return $this;
        }

        public function tag(string $sName):self {
            $this->Tags[] = $sName;
            return $this;
        }

        public function inTable(Table $oTable):self {
            return $this->queryParams(self::tableToParams($oTable));
        }

        public function responseSchema(array $aSchema):self {
            $this->ResponseSchema = $aSchema;
            return $this;
        }

        public function responseReference(string $aReference):self {
            $this->ResponseReference = $aReference;
            return $this;
        }

        /**
         * @param Table $oTable
         * @return Param[]
         */
        public static function tableToParams(Table $oTable) {
            $aDefinitions = [];
            $aFields = $oTable->getColumnsWithFields();

            foreach($aFields as $oField) {
                $oParam = self::fieldToParam($oTable, $oField);
                if ($oParam instanceof Param) {
                    $aDefinitions[$oParam->sName] = $oParam;
                }
            }

            return $aDefinitions;
        }

        /**
         * @param Table $oTable
         * @param Field $oField
         * @return Param
         */
        public static function fieldToParam(Table $oTable, Field $oField): ?Param {
            switch(true) {
                default:
                case $oField instanceof Field\Text:    $iType = Param::STRING;  break;
                case $oField instanceof Field\Boolean: $iType = Param::BOOLEAN; break;
                case $oField instanceof Field\Integer: $iType = Param::INTEGER; break;
                case $oField instanceof Field\Number:  $iType = Param::NUMBER;  break;
            }

            $sField = DataMap::getPublicName($oTable, $oField->sColumn);
            if (!$sField) {
                return null;
            }

            $aValidations = [];

            switch(true) {
                case $oField instanceof Field\Enum:
                    $aValidations['enum'] = $oField->aValues;
                    break;

                case $oField instanceof Field\TextNullable:
                    $aValidations['nullable'] = true;
                    break;

                case $oField instanceof Field\DateTime:
                    $aValidations['format'] = "date-time";
                    break;

                case $oField instanceof Field\Date:
                    $aValidations['format'] = "date";
                    break;
            }

            if (strpos(strtolower($oField->sColumn), 'password') !== false) {
                $aValidations['format'] = "password";
            }

            if ($oField->hasDefault()) {
                $aValidations['default'] = $oField->sDefault;
            }

            return new Param($sField, $iType, $aValidations);
        }

        public function inHeaders(array $aHeaders):self {
            $this->InHeaders = $aHeaders;
            return $this;
        }

        public function outHeaders(array $aHeaders):self {
            $this->OutHeaders = $aHeaders;
            return $this;
        }

        public function codeSample(string $sLanguage, string $sSource):self {
            $this->CodeSamples[$sLanguage] = $sSource;
            return $this;
        }

        /**
         * @throws DocumentationException
         * @throws InvalidRequest
         */
        public function validateRequest(Request $oRequest,  Response $oResponse) {
            $this->RequestValidated = true;

            // TODO: Validate Path Params

            $aParameters = [];
            switch ($this->HttpMethod) {
                case Method\GET:  $aParameters = $oRequest->GET;  break;
                case Method\POST: $aParameters = $oRequest->POST; break;
            }

            $oParameters = (object) $aParameters;
            $oValidator  = new Validator;
            $oValidator->validate(
                $oParameters,
                self::paramsToJsonSchema($this->QueryParams)->all(),
                Constraint::CHECK_MODE_APPLY_DEFAULTS | Constraint::CHECK_MODE_ONLY_REQUIRED_DEFAULTS
            );

            if (!$oValidator->isValid()) {
                $oDot = new Dot();
                $oDot->set('parameters', $aParameters);

                $aErrors = [];
                foreach($oValidator->getErrors() as $aError) {
                    $aError['value'] = $oDot->get($aError['property']);
                    $aErrors[]       = $aError;
                }

                $oResponse->add('_request.validation.status', 'FAIL');
                $oResponse->add('_request.validation.errors', $aErrors);

                throw new InvalidRequest();
            } else {
                $oResponse->add('_request.validation.status', 'PASS');
                $oRequest->ValidatedParams = (array) $oParameters;
            }
        }

        public function paramsToResponseSchema(array $aParams): Dot {
            if (count($aParams) && isset($aParams['type']) && isset($aParams['properties'])) { // JSONSchema
                $oSchema = new Dot($aParams);
            } else {
                $oSchema = self::paramsToJsonSchema($aParams);
            }

            $oSchema->set("properties._server", ['$ref' => "#/components/schemas/_server"]);
            $oSchema->set("properties._request", ['$ref' => "#/components/schemas/_request"]);

            return $oSchema;
        }

        /**
         * @param array|Param[] $aParams
         * @return Dot
         */
        public static function paramsToJsonSchema(array $aParams): Dot {
            $oSchema = new Dot([
                "type" => "object",
                "additionalProperties" => false
            ]);

            /** @var Param $oParam */
            foreach ($aParams as $oParam) {
                $sName = $oParam->sName;

                if (strpos($sName, '.') !== false) {
                    $aName = explode(".", $sName);
                    $sFullName = implode(".properties.", $aName);

                    $oSchema->set("properties.$sFullName", $oParam->JsonSchema());

                    if ($oParam->required()) {
                        $aParent = explode(".", $sName);
                        array_pop($aParent);
                        $sParent = implode(".properties.", $aParent);

                        $aKid = explode(".", $sName);
                        array_shift($aKid);
                        $sKid = implode(".", $aKid);

                        $oSchema->push("properties.$sParent.required", $sKid);
                    }
                } else {
                    $oSchema->set("properties.$sName", $oParam->JsonSchema());
                    if ($oParam->required()) {
                        $oSchema->push('required', $oParam->sName);
                    }
                }
            }

            return $oSchema;
        }

        public function generateOpenAPI(): array {
            $aMethod = [
                'summary'       => $this->Summary ?? $this->Path,
                'description'   => $this->Description ?? $this->Summary ?? $this->Path,
                'tags'          => $this->Tags
            ];

            if (count($this->Scopes)) {
                $aMethod['security'] = [["OAuth2" => $this->Scopes]];
            }

            if ($this->Deprecated) {
                $aMethod['deprecated'] = true;
            }

            $oQueryJsonParams = self::paramsToJsonSchema($this->QueryParams);
            $aParameters   = [];

            foreach($this->QueryParams as $oParam) {
                if (strpos($oParam->sName, '.') !== false) {
                    continue;
                }

                if ($oParam->is(Param::OBJECT)) {
                    $aParam = $oParam->OpenAPI('query');
                    $aParam['schema'] = $oQueryJsonParams->get("properties.{$oParam->sName}");
                    $aParameters[] = $aParam;
                } else {
                    $aParameters[] = $oParam->OpenAPI('query');
                }
            }

            $oPathJsonParams = self::paramsToJsonSchema($this->PathParams);

            foreach($this->PathParams as $oParam) {
                if (strpos($oParam->sName, '.') !== false) {
                    continue;
                }

                if ($oParam->is(Param::OBJECT)) {
                    $aParam = $oParam->OpenAPI('path');
                    $aParam['schema'] = $oPathJsonParams->get("properties.{$oParam->sName}");
                    $aParam['required'] = true;
                    $aParameters[] = $aParam;
                } else {
                    $aParameters[] = $oParam->OpenAPI('path');
                }
            }

            if (count($aParameters)) {
                $aMethod['parameters'] = $aParameters;
            }

            $aResponses = [];

            if ($this->ResponseSchema) {
                $aResponses = [$this->ResponseSchema];
            } else if ($this->ResponseReference) {
                $aResponses[] = ['$ref' => "#/components/schemas/_default"];
                $aResponses[] = ['$ref' => $this->ResponseReference];
            }

            if (!count($aResponses)) {
                $aResponses[] = ['$ref' => "#/components/schemas/_default"];
            }

            $aMethod['responses'] = [];

            foreach($this->Responses as $iStatus => $sDescription) {
                if ($iStatus === HTTP\OK) {
                    $aMethod['responses'][$iStatus] = [
                        "description" => $sDescription,
                        "content" => [
                            "application/json" => [
                                "schema" => [
                                    "allOf" => $aResponses,
                                ]
                            ]
                        ]
                    ];
                } else {
                    $aMethod['responses'][$iStatus] = [
                        "description" => $sDescription
                    ];
                }
            }


            if (count($aParameters)) {
                $aMethod['responses'][HTTP\BAD_REQUEST] = [
                    "description" => "Problem with Request.  See `_request.validation` for details"
                ];
            }

            if (count($this->CodeSamples)) {
                foreach($this->CodeSamples as $sLanguage => $sSource) {
                    $aMethod['x-code-samples'][] = [
                        'lang'   => $sLanguage,
                        'source' => str_replace('{{PATH}}', $this->Path, $sSource)
                    ];
                }
            }

            return $aMethod;
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