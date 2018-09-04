<?php
    namespace Enobrev\API;

    use Adbar\Dot;
    use Enobrev\API\FullSpec\ComponentInterface;
    use Enobrev\API\FullSpec\Component\Reference;
    use Enobrev\API\FullSpec\Component\Response;
    use Enobrev\API\Spec\JsonResponse;
    use function Enobrev\array_not_associative;
    use function Enobrev\dbg;
    use JsonSchema\Constraints\Constraint;
    use JsonSchema\Validator;

    use Enobrev\API\Exception\InvalidRequest;
    use Enobrev\ORM\Table;
    use Enobrev\ORM\Field;
    use Enobrev\API\HTTP;

    class Spec {
        const SKIP_PRIMARY = 1;

        /** @var string */
        private $sSummary;

        /** @var string */
        private $sDescription;

        /** @var boolean */
        private $bRequestValidated = false;

        /** @var boolean */
        private $bDeprecated;

        /** @var string */
        private $sPath;

        /** @var boolean */
        private $bPublic = false;

        /** @var string */
        private $sHttpMethod;

        /** @var string */
        private $sMethod;

        /** @var string[] */
        private $aScopes;

        /** @var Param[] */
        private $aPathParams = [];

        /** @var Param[] */
        private $aQueryParams = [];

        /** @var Param[] */
        private $aPostParams = [];

        /** @var OpenApiInterface */
        private $oPostBodyReference;

        /** @var array */
        private $aResponseSchema;

        /** @var string */
        private $sResponseReference;

        /** @var Param[] */
        private $aInHeaders = [];

        /** @var Param[] */
        private $aOutHeaders = [];

        /** @var array */
        private $aCodeSamples = [];

        /** @var array */
        private $aResponseHeaders = [];

        /** @var string[] */
        private $aTags = [];

        /** @var array */
        private $aResponses = [];

        public static function create() {
            return new self();
        }

        public function getPath():string {
            return $this->sPath;
        }

        public function getHttpMethod():string {
            return $this->sHttpMethod;
        }

        public function getLowerHttpMethod():string {
            return strtolower($this->sHttpMethod);
        }

        public function getScopeList(string $sDivider = ' '): string {
            return implode($sDivider, $this->aScopes);
        }

        public function getResponseDescription(int $iStatus) {
            $mResponse = $this->aResponses[$iStatus] ?? null;

            if (!$mResponse) {
                throw new Exception('Invalid Status');
            }

            if ($mResponse instanceof Response) {
                return $mResponse->getDescription();
            } else if (is_string($mResponse)) {
                return $mResponse;
            }

            throw new Exception('Not Sure What the Response Description Is');
        }

        public function getResponse(int $iStatus) {
            return $this->aResponses[$iStatus] ?? null;
        }

        public function hasAnyOfTheseScopes(array $aScopes): bool {
            if (count($this->aScopes) === 0) {
                return false;
            }

            return count(array_intersect($aScopes, $this->aScopes)) > 0;
        }

        public function hasAllOfTheseScopes(array $aScopes): bool {
            if (count($this->aScopes)) {
                return false;
            }

            return count(array_intersect($aScopes, $this->aScopes)) == count($aScopes);
        }

        /**
         * @return Param[]
         */
        public function getPathParams(): array {
            return $this->aPathParams;
        }

        /**
         * @return Param[]
         */
        public function getQueryParams(): array {
            return $this->aQueryParams;
        }

        /**
         * @return Param[]
         */
        public function getPostParams(): array {
            return $this->aPostParams;
        }

        public function isPublic():bool {
            return $this->bPublic;
        }

        public function pathParamsToJsonSchema():array {
            return self::toJsonSchema($this->aPathParams);
        }

        public function queryParamsToJsonSchema():array {
            return self::toJsonSchema($this->aQueryParams);
        }

        public function postParamsToJsonSchema():array {
            return self::toJsonSchema($this->aPostParams);
        }

        public function summary(string $sSummary):self {
            $this->sSummary = $sSummary;
            return $this;
        }

        public function description(string $sDescription):self {
            $this->sDescription = $sDescription;
            return $this;
        }

        public function deprecated(?bool $bDeprecated = true):self {
            $this->bDeprecated = $bDeprecated;
            return $this;
        }

        public function path(string $sPath):self {
            $this->sPath = $sPath;
            return $this;
        }

        public function httpMethod(string $sHttpMethod):self {
            $this->sHttpMethod = $sHttpMethod;
            return $this;
        }

        public function method(string $sMethod):self {
            $this->sMethod = $sMethod;
            return $this;
        }

        /**
         * @param array $aScopes
         * @return Spec
         * @throws Exception
         */
        public function scopes(array $aScopes):self {
            if (!array_not_associative($aScopes)) {
                throw new Exception('Please define Scopes as a non-Associative Array');
            }
            $this->aScopes = $aScopes;
            return $this;
        }

        public function setPublic(bool $bPublic = true):self {
            $this->bPublic = $bPublic;
            return $this;
        }

        public function pathParams(array $aParams):self {
            $this->aPathParams = $aParams;
            return $this;
        }

        public function queryParams(array $aParams):self {
            $this->aQueryParams = $aParams;
            return $this;
        }

        public function postBodyReference(ComponentInterface $oReference):self {
            $this->oPostBodyReference = $oReference;
            return $this;
        }

        public function postParams(array $aParams):self {
            $this->aPostParams = $aParams;
            return $this;
        }

        public function responseHeader(string $sHeader, string $sValue):self {
            $this->aResponseHeaders[$sHeader] = $sValue;
            return $this;
        }

        public function removeResponse(int $iStatus):self {
            unset($this->aResponses[$iStatus]);
            return $this;
        }

        public function response($iStatus, $mResponse = null):self {
            $this->aResponses[$iStatus] = $mResponse;
            return $this;
        }

        public function tags(array $aTags):self {
            $this->aTags += $aTags;
            $this->aTags = array_unique($aTags);
            return $this;
        }

        public function tag(string $sName):self {
            $this->aTags[] = $sName;
            return $this;
        }

        public function inTable(Table $oTable):self {
            return $this->queryParams(self::tableToParams($oTable));
        }

        public function responseSchema(array $aSchema):self {
            $this->aResponseSchema = $aSchema;
            return $this;
        }

        public function responseReference(string $aReference):self {
            $this->sResponseReference = $aReference;
            return $this;
        }

        public static function tableToJsonSchema(Table $oTable, int $iOptions = 0, array $aExclude = []): array {
            return self::toJsonSchema(self::tableToParams($oTable, $iOptions, $aExclude));
        }

        /**
         * @param Table $oTable
         * @param int $iOptions
         * @param array $aExclude
         * @return Param[]
         */
        public static function tableToParams(Table $oTable, int $iOptions = 0, array $aExclude = []) {
            $aDefinitions = [];
            $aFields = $oTable->getColumnsWithFields();

            foreach($aFields as $oField) {
                if ($iOptions & self::SKIP_PRIMARY && $oField->isPrimary()) {
                    continue;
                }

                if (in_array($oField->sColumn, $aExclude)) {
                    continue;
                }

                $sField = DataMap::getPublicName($oTable, $oField->sColumn);
                if (!$sField) {
                    continue;
                }

                $oParam = self::fieldToParam($oField);
                if ($oParam instanceof Param) {
                    $aDefinitions[$sField] = $oParam;
                }
            }

            return $aDefinitions;
        }

        /**
         * @param Table $oTable
         * @param Field $oField
         * @return Param
         */
        public static function fieldToParam(Field $oField): ?Param {
            switch(true) {
                default:
                case $oField instanceof Field\Text:    $oParam = Param\_String::create();  break;
                case $oField instanceof Field\Boolean: $oParam = Param\_Boolean::create(); break;
                case $oField instanceof Field\Integer: $oParam = Param\_Integer::create(); break;
                case $oField instanceof Field\Number:  $oParam = Param\_Number::create();  break;
            }

            switch(true) {
                case $oField instanceof Field\Enum:
                    $oParam->enum($oField->aValues);
                    break;

                case $oField instanceof Field\TextNullable:
                    $oParam->nullable();
                    break;

                case $oField instanceof Field\DateTime:
                    $oParam->format('date-time');
                    break;

                case $oField instanceof Field\Date:
                    $oParam->format('date');
                    break;
            }

            if (strpos(strtolower($oField->sColumn), 'password') !== false) {
                $oParam->format('password');
            }

            if ($oField->hasDefault()) {
                if ($oField instanceof Field\Boolean) {
                    $oParam->default((bool) $oField->sDefault);
                } else {
                    $oParam->default($oField->sDefault);
                }
            }

            return $oParam;
        }

        public function inHeaders(array $aHeaders):self {
            $this->aInHeaders = $aHeaders;
            return $this;
        }

        public function outHeaders(array $aHeaders):self {
            $this->aOutHeaders = $aHeaders;
            return $this;
        }

        public function codeSample(string $sLanguage, string $sSource):self {
            $this->aCodeSamples[$sLanguage] = $sSource;
            return $this;
        }

        /**
         * @param Request $oRequest
         * @param Response $oResponse
         * @throws InvalidRequest
         */
        public function validateRequest(Request $oRequest,  Response $oResponse) {
            $this->bRequestValidated = true;

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

            $this->validateParameters($this->aPathParams, $aParameters, $oResponse);
        }

        /**
         * @param Request $oRequest
         * @param Response $oResponse
         * @throws InvalidRequest
         */
        private function validateQueryParameters(Request $oRequest, Response $oResponse) {
            $aParameters = $oRequest->queryParams();

            $this->validateParameters($this->aQueryParams, $aParameters, $oResponse);
        }

        /**
         * @param array $aParameters
         * @param Response $oResponse
         * @throws InvalidRequest
         */
        private function validateParameters(array $aSpecParameters, array $aParameters, Response $oResponse) {
            // Coerce CSV Params
            foreach($aSpecParameters as $oParam) {
                if ($oParam->is(Param::ARRAY)) {
                    if (isset($aParameters[$oParam->sName])) {
                        $aParameters[$oParam->sName] = explode(',', $aParameters[$oParam->sName]);
                    }
                }
            }

            $oParameters = (object) $aParameters;
            $oValidator  = new Validator;
            $oValidator->validate(
                $oParameters,
                self::toJsonSchema($aSpecParameters),
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
                $oSchema = new Dot(self::toJsonSchema($aParams));
            }

            $oSchema->set("properties._server", ['$ref' => "#/components/schemas/_server"]);
            $oSchema->set("properties._request", ['$ref' => "#/components/schemas/_request"]);

            return $oSchema;
        }

        /**
         * @param array|Param[] $aParams
         * @return Dot
         */
        public static function toJsonSchema(array $aArray): array {
            if (isset($aArray['type']) && in_array($aArray['type'], ['object', 'array', 'integer', 'number', 'boolean', 'string'])) {
                // this is likely already a jsonschema
                return $aArray;
            }

            $oResponse = new Dot([
                'type'                  => 'object',
                'additionalProperties'  => false,
                'properties'            => []
            ]);

            /** @var Param $oParam */
            foreach ($aArray as $sName => $mValue) {
                if (strpos($sName, '.') !== false) {
                    $aName    = explode('.', $sName);
                    $sSubName = array_shift($aName);

                    $oDot = new Dot();
                    $oDot->set(implode('.', $aName), $mValue);
                    $aValue = $oDot->all();

                    $oResponse->set("properties.$sSubName", self::toJsonSchema($aValue));
                } else if ($mValue instanceof JsonSchemaInterface) {
                    $oResponse->set("properties.$sName", $mValue->getJsonSchema());

                    if ($mValue instanceof Param && $mValue->isRequired()) {
                        $oResponse->push('required', $sName);
                    }
                } else if ($mValue instanceof Dot) {
                    $aValue = $mValue->all();
                    $oResponse->set("properties.$sName", self::toJsonSchema($aValue));
                } else if (is_array($mValue)) {
                    $oResponse->set("properties.$sName", self::toJsonSchema($mValue));
                } else {
                    $oResponse->set("properties.$sName", $mValue);
                }
            }

            return $oResponse->all();
        }

        public function generateOpenAPI(): array {
            $aMethod = [
                'summary'       => $this->sSummary ?? $this->sPath,
                'description'   => $this->sDescription ?? $this->sSummary ?? $this->sPath,
                'tags'          => $this->aTags
            ];

            if (!$this->bPublic && count($this->aScopes)) {
                $aMethod['security'] = [["OAuth2" => $this->aScopes]];
            }

            if ($this->bDeprecated) {
                $aMethod['deprecated'] = true;
            }

            $oPathJsonParams = new Dot(self::toJsonSchema($this->aPathParams));
            $aParameters   = [];

            foreach($this->aPathParams as $sParam => $oParam) {
                if (strpos($sParam, '.') !== false) {
                    continue;
                }

                if ($oParam instanceof Param\_Object) {
                    $aParam = $oParam->OpenAPI($sParam, 'path');
                    $aParam['schema'] = $oPathJsonParams->get("properties.$sParam");
                    $aParam['required'] = true;
                    $aParameters[] = $aParam;
                } else {
                    $aParameters[] = $oParam->OpenAPI($sParam, 'path');
                }
            }

            $oQueryJsonParams = new Dot(self::toJsonSchema($this->aQueryParams));

            foreach($this->aQueryParams as $sParam => $oParam) {
                if (strpos($sParam, '.') !== false) {
                    continue;
                }

                if ($oParam instanceof Param\_Object) {
                    $aParam = $oParam->OpenAPI($sParam, 'query');
                    $aParam['schema'] = $oQueryJsonParams->get("properties.{$sParam}");
                    $aParameters[] = $aParam;
                } else {
                    $aParameters[] = $oParam->OpenAPI($sParam, 'query');
                }
            }

            if (count($aParameters)) {
                $aMethod['parameters'] = $aParameters;
            }

            if ($this->oPostBodyReference) {
                $aMethod['requestBody'] = $this->oPostBodyReference->getOpenAPI();
            } else {
                $oPostJsonParams = new Dot(self::toJsonSchema($this->aPostParams));
                $aPost = [];

                foreach ($this->aPostParams as $sParam => $oParam) {
                    if (strpos($sParam, '.') !== false) {
                        continue;
                    }

                    if ($oParam instanceof Param\_Object) {
                        $aParam = $oParam->OpenAPI($sParam, null);
                        $aParam['schema'] = $oPostJsonParams->get("properties.{$sParam}");
                        $aPost[$sParam] = $aParam;
                    } else {
                        $aPost[$sParam] = $oParam->getJsonSchema();
                    }
                }

                if (count($aPost)) {
                    $aMethod['requestBody'] = [
                        "content" => [
                            'multipart/form-data' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => $aPost
                                ]
                            ]
                        ]
                    ];
                }
            }

            $aMethod['responses'] = [];

            foreach($this->aResponses as $iStatus => $oResponse) {
                if ($oResponse instanceof OpenApiResponseSchemaInterface) {
                    $aMethod['responses'][$iStatus] = [
                        "description" => $iStatus . ' Response',
                        "content" => [
                            "application/json" => [
                                'schema' => $oResponse->getOpenAPI()
                            ]
                        ]
                    ];
                } else if ($oResponse instanceof OpenApiInterface) {
                    $aMethod['responses'][$iStatus] = $oResponse->getOpenAPI();
                } else  {
                    $aMethod['responses'][$iStatus] = Reference::create(FullSpec::RESPONSE_DEFAULT)->getOpenAPI();
                }
                    /*
                } else if ($this->aResponseSchema) {
                    $aMethod['responses'][$iStatus] = [
                        "description" => $mStatus,
                        "content" => [
                            "application/json" => [
                                "schema" => $this->aResponseSchema
                            ]
                        ]
                    ];
                } else if ($this->sResponseReference) {
                    $aMethod['responses'][$iStatus] = ['$ref' => $this->sResponseReference];
                */
            }


            if (count($aParameters) && !isset($aMethod['responses'][HTTP\BAD_REQUEST])) {
                $aMethod['responses'][HTTP\BAD_REQUEST] = Reference::create(FullSpec::RESPONSE_BAD_REQUEST)->getOpenAPI();
            }

            if (isset($aMethod['security'])) {
                if (!isset($aMethod['responses'][HTTP\UNAUTHORIZED])) {
                    $aMethod['responses'][HTTP\UNAUTHORIZED] = Reference::create(FullSpec::RESPONSE_UNAUTHORIZED)->getOpenAPI();
                }

                if (!isset($aMethod['responses'][HTTP\FORBIDDEN])) {
                    $aMethod['responses'][HTTP\FORBIDDEN] = Reference::create(FullSpec::RESPONSE_FORBIDDEN)->getOpenAPI();
                }
            }

            if (!isset($aMethod['responses'][HTTP\INTERNAL_SERVER_ERROR])) {
                $aMethod['responses'][HTTP\INTERNAL_SERVER_ERROR] = Reference::create(FullSpec::RESPONSE_SERVER_ERROR)->getOpenAPI();
            }

            if (count($this->aCodeSamples)) {
                foreach($this->aCodeSamples as $sLanguage => $sSource) {
                    $aMethod['x-code-samples'][] = [
                        'lang'   => $sLanguage,
                        'source' => str_replace('{{PATH}}', $this->sPath, $sSource)
                    ];
                }
            }

            return $aMethod;
        }

        public function toArray() {
            $aPathParams = [];
            foreach($this->aPathParams as $sParam => $oParam) {
                $aPathParams[$sParam] = $oParam->getJsonSchema();
            }
            
            $aQueryParams = [];
            foreach($this->aQueryParams as $sParam => $oParam) {
                $aQueryParams[$sParam] = $oParam->getJsonSchema();
            }

            $aPostParams = [];
            foreach($this->aPostParams as $sParam => $oParam) {
                $aPostParams[$sParam] = $oParam->getJsonSchema();
            }
            
            return [
                'Summary'           => $this->sSummary,
                'Description'       => $this->sDescription,
                'RequestValidated'  => $this->bRequestValidated,
                'Deprecated'        => $this->bDeprecated,
                'Path'              => $this->sPath,
                'Public'            => $this->bPublic,
                'HttpMethod'        => $this->sHttpMethod,
                'Method'            => $this->sMethod,
                'Scopes'            => $this->aScopes,
                'PathParams'        => $aPathParams,
                'QueryParams'       => $aQueryParams,
                'PostParams'        => $aPostParams,
                'ResponseSchema'    => $this->aResponseSchema,
                'ResponseReference' => $this->sResponseReference,
                'InHeaders'         => $this->aInHeaders,
                'OutHeaders'        => $this->aOutHeaders,
                'CodeSamples'       => $this->aCodeSamples,
                'ResponseHeaders'   => $this->aResponseHeaders,
                'Responses'         => $this->aResponses,
                'Tags'              => $this->aTags
            ];
        }
        
        public function toJson() {
            return json_encode($this->toArray());
        }
    }