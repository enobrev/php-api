<?php
    namespace Enobrev\API;

    use Adbar\Dot;
    use Enobrev\API\Exception\InvalidRequest;
    use Enobrev\ORM\Table;
    use Enobrev\ORM\Field;
    use JsonSchema\Constraints\Constraint;
    use JsonSchema\Validator;

    class Spec {
        const SKIP_PRIMARY = 1;

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

        /** @var boolean */
        public $Public = false;

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

        public static function create() {
            return new self();
        }

        public function handle(Request $oRequest, Response $oResponse) {
            // Override Me
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

        public function isPublic(bool $bPublic = true):self {
            $this->Public = $bPublic;
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
         * @param int $iOptions
         * @return Param[]
         */
        public static function tableToParams(Table $oTable, int $iOptions = 0) {
            $aDefinitions = [];
            $aFields = $oTable->getColumnsWithFields();

            foreach($aFields as $oField) {
                if ($iOptions & self::SKIP_PRIMARY && $oField->isPrimary()) {
                    continue;
                }

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
         * @param Request $oRequest
         * @param Response $oResponse
         * @throws InvalidRequest
         */
        public function validateRequest(Request $oRequest,  Response $oResponse) {
            $this->RequestValidated = true;

            $this->validatePathParameters($oRequest, $oResponse);
            $this->validateQueryParameters($oRequest, $oResponse);
        }

        /**
         * @param Request $oRequest
         * @param Response $oResponse
         * @throws InvalidRequest
         */
        private function validatePathParameters(Request $oRequest, Response $oResponse) {
            $aParameters = $oRequest->pathParams();

            // Coerce CSV Params
            foreach($this->PathParams as $oParam) {
                if ($oParam->is(Param::ARRAY)) {
                    if (isset($aParameters[$oParam->sName])) {
                        $aParameters[$oParam->sName] = explode(',', $aParameters[$oParam->sName]);
                    }
                }
            }

            $this->validateParameters($aParameters, $oResponse);
        }

        /**
         * @param Request $oRequest
         * @param Response $oResponse
         * @throws InvalidRequest
         */
        private function validateQueryParameters(Request $oRequest, Response $oResponse) {
            $aParameters = $oRequest->queryParams();

            // Coerce CSV Params
            foreach($this->QueryParams as $oParam) {
                if ($oParam->is(Param::ARRAY)) {
                    if (isset($aParameters[$oParam->sName])) {
                        $aParameters[$oParam->sName] = explode(',', $aParameters[$oParam->sName]);
                    }
                }
            }

            $this->validateParameters($aParameters, $oResponse);
        }

        /**
         * @param array $aParameters
         * @param Response $oResponse
         * @throws InvalidRequest
         */
        private function validateParameters(array $aParameters,  Response $oResponse) {
            $oParameters = (object) $aParameters;
            $oValidator  = new Validator;
            $oValidator->validate(
                $oParameters,
                self::paramsToJsonSchema($this->PathParams)->all(),
                Constraint::CHECK_MODE_APPLY_DEFAULTS | Constraint::CHECK_MODE_ONLY_REQUIRED_DEFAULTS | Constraint::CHECK_MODE_COERCE_TYPES
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
                //$oRequest->ValidatedParams = (array) $oParameters;
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

            if (!$this->Public && count($this->Scopes)) {
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

            $aMethod['responses'] = [];

            foreach($this->Responses as $iStatus => $sDescription) {
                if ($iStatus === HTTP\OK) {
                    if ($this->ResponseSchema) {
                        $aMethod['responses'][$iStatus] = [
                            "description" => $sDescription,
                            "content" => [
                                "application/json" => [
                                    "schema" => $this->ResponseSchema
                                ]
                            ]
                        ];
                    } else if ($this->ResponseReference) {
                        $aMethod['responses'][$iStatus] = ['$ref' => $this->ResponseReference];
                    } else {
                        $aMethod['responses'][$iStatus] = [
                            "description" => $sDescription,
                            "content" => [
                                "application/json" => [
                                    "schema" => ['$ref' => "#/components/schemas/_default"]
                                ]
                            ]
                        ];
                    }
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

        public function toArray() {
            $aPathParams = [];
            foreach($this->PathParams as $sParam => $oParam) {
                $PathParams[$sParam] = $oParam->JsonSchema();
            }
            
            $aQueryParams = [];
            foreach($this->QueryParams as $sParam => $oParam) {
                $QueryParams[$sParam] = $oParam->JsonSchema();
            }
            
            return [
                'Summary'           => $this->Summary,
                'Description'       => $this->Description,
                'RequestValidated'  => $this->RequestValidated,
                'Deprecated'        => $this->Deprecated,
                'Path'              => $this->Path,
                'Public'            => $this->Public,
                'HttpMethod'        => $this->HttpMethod,
                'Method'            => $this->Method,
                'Scopes'            => $this->Scopes,
                'PathParams'        => $aPathParams,
                'QueryParams'       => $aQueryParams,
                'ResponseSchema'    => $this->ResponseSchema,
                'ResponseReference' => $this->ResponseReference,
                'InHeaders'         => $this->InHeaders,
                'OutHeaders'        => $this->OutHeaders,
                'CodeSamples'       => $this->CodeSamples,
                'ResponseHeaders'   => $this->ResponseHeaders,
                'Responses'         => $this->Responses,
                'Tags'              => $this->Tags
            ];
        }
        
        public function toJson() {
            return json_encode($this->toArray());
        }
    }