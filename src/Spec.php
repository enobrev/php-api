<?php
    namespace Enobrev\API;

    use Adbar\Dot;
    use Enobrev\API\Spec\ProcessErrorResponse;
    use function Enobrev\array_not_associative;
    use function Enobrev\dbg;
    use JsonSchema\Constraints\Constraint;
    use JsonSchema\Validator;

    use Enobrev\API\Exception\InvalidRequest;
    use Enobrev\API\FullSpec\ComponentInterface;
    use Enobrev\API\FullSpec\Component\Reference;
    use Enobrev\API\FullSpec\Component\Response;
    use Enobrev\API\HTTP;
    use Enobrev\API\Spec\ErrorResponseInterface;
    use Enobrev\ORM\Field;
    use Enobrev\ORM\Table;
    use Middlewares\HttpErrorException;

    class Spec {
        const SKIP_PRIMARY = 1;

        /** @var string */
        private $sSummary;

        /** @var string */
        private $sDescription;

        /**
         * @var boolean
         * @deprecated
         */
        private $bRequestValidated = false;

        /**
         * @var boolean
         */
        private $bSkipDefaultResponses = false;

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

        /** @var Param[] */
        private $aHeaderParams = [];

        /** @var OpenApiInterface */
        private $oPostBodyReference;

        /**
         * @var array
         * @deprecated
         */
        private $aResponseSchema;

        /**
         * @var string
         * @deprecated
         */
        private $sResponseReference;

        /** @var array */
        private $aResponseHeaders = [];

        /** @var array */
        private $aCodeSamples = [];

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
            } else if (is_array($mResponse)) {
                $aDescription = [];
                foreach($mResponse as $mSubResponse) {
                    if ($mSubResponse instanceof Response) {
                        $aDescription[] = $mSubResponse->getDescription();
                    } else if (is_string($mSubResponse)) {
                        $aDescription[] = $mSubResponse;
                    }
                }
                return $aDescription;
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

        public function hasAPostBodyReference(): bool {
            return $this->oPostBodyReference instanceof Reference;
        }

        public function postParamsToJsonSchema():array {
            if ($this->oPostBodyReference && $this->oPostBodyReference instanceof Reference) {
                // FIXME: This is a big fat hack
                $oFullSpec  = FullSpec::getFromCache();
                $oComponent = $oFullSpec->followTheYellowBrickRoad($this->oPostBodyReference);
                if ($oComponent instanceof OpenApiInterface) {
                    return $oComponent->getOpenAPI();
                }
            }

            return self::toJsonSchema($this->aPostParams);
        }

        public function summary(string $sSummary):self {
            $oClone = clone $this;
            $oClone->sSummary = $sSummary;
            return $oClone;
        }

        public function description(string $sDescription):self {
            $oClone = clone $this;
            $oClone->sDescription = $sDescription;
            return $oClone;
        }

        public function deprecated(?bool $bDeprecated = true):self {
            $oClone = clone $this;
            $oClone->bDeprecated = $bDeprecated;
            return $oClone;
        }

        public function skipDefaultResponses(?bool $bSkipDefaultResponses = true):self {
            $oClone = clone $this;
            $oClone->bSkipDefaultResponses = $bSkipDefaultResponses;
            return $oClone;
        }

        public function path(string $sPath):self {
            $oClone = clone $this;
            $oClone->sPath = $sPath;
            return $oClone;
        }

        public function httpMethod(string $sHttpMethod):self {
            $oClone = clone $this;
            $oClone->sHttpMethod = $sHttpMethod;
            return $oClone;
        }

        public function method(string $sMethod):self {
            $oClone = clone $this;
            $oClone->sMethod = $sMethod;
            return $oClone;
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

            $oClone = clone $this;
            $oClone->aScopes = $aScopes;
            return $oClone;
        }

        public function setPublic(bool $bPublic = true):self {
            $oClone = clone $this;
            $oClone->bPublic = $bPublic;
            return $oClone;
        }

        /**
         * @param Param[] $aParams
         * @return Spec
         */
        public function pathParams(array $aParams):self {
            $oClone = clone $this;
            $oClone->aPathParams = $aParams;
            return $oClone;
        }

        /**
         * @param Param[] $aParams
         * @return Spec
         */
        public function queryParams(array $aParams):self {
            $oClone = clone $this;
            $oClone->aQueryParams = $aParams;
            return $oClone;
        }

        /**
         * @param Param[] $aParams
         * @return Spec
         */
        public function headerParams(array $aParams):self {
            $oClone = clone $this;
            $oClone->aHeaderParams = $aParams;
            return $oClone;
        }

        public function postBodyReference(ComponentInterface $oReference):self {
            $oClone = clone $this;
            $oClone->oPostBodyReference = $oReference;
            return $oClone;
        }

        public function postParams(array $aParams):self {
            $oClone = clone $this;
            $oClone->aPostParams = $aParams;
            return $oClone;
        }

        public function responseHeader(string $sHeader, string $sValue):self {
            $oClone = clone $this;
            $oClone->aResponseHeaders[$sHeader] = $sValue;
            return $oClone;
        }

        /**
         * @param int $iStatus
         * @return Spec
         * @deprecated
         */
        public function withoutResponse(int $iStatus):self {
            $oClone = clone $this;
            unset($oClone->aResponses[$iStatus]);
            return $oClone;
        }

        public function response($iStatus, $mResponse = null):self {
            $oClone = clone $this;
            if (!isset($this->aResponses[$iStatus])) {
                $oClone->aResponses[$iStatus] = [];
            }

            $oClone->aResponses[$iStatus][] = $mResponse;
            return $oClone;
        }

        public function responseFromException(HttpErrorException $oException) {
            $oResponse = ProcessErrorResponse::createFromException($oException);
            return $this->response($oResponse->getCode(), $oResponse);
        }

        public function tags(array $aTags):self {
            $oClone = clone $this;
            $oClone->aTags += $aTags;
            $oClone->aTags = array_unique($aTags);
            return $oClone;
        }

        public function tag(string $sName):self {
            $oClone = clone $this;
            $oClone->aTags[] = $sName;
            return $oClone;
        }

        public function codeSample(string $sLanguage, string $sSource):self {
            $oClone = clone $this;
            $oClone->aCodeSamples[$sLanguage] = $sSource;
            return $oClone;
        }

        public function inTable(Table $oTable):self {
            return $this->queryParams(self::tableToParams($oTable));
        }

        /**
         * @param array $aSchema
         * @return Spec
         * @deprecated
         */
        public function responseSchema(array $aSchema):self {
            $oClone = clone $this;
            $oClone->aResponseSchema = $aSchema;
            return $oClone;
        }

        /**
         * @param string $aReference
         * @return Spec
         * @deprecated
         */
        public function responseReference(string $aReference):self {
            $oClone = clone $this;
            $oClone->sResponseReference = $aReference;
            return $oClone;
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

        /**
         * @param Request $oRequest
         * @param Response $oResponse
         * @throws InvalidRequest
         * @deprecated
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
         * @deprecated
         */
        private function validateQueryParameters(Request $oRequest, Response $oResponse) {
            $aParameters = $oRequest->queryParams();

            $this->validateParameters($this->aQueryParams, $aParameters, $oResponse);
        }

        /**
         * @param array $aParameters
         * @param Response $oResponse
         * @throws InvalidRequest
         * @deprecated
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

                    $oResponse->mergeRecursiveDistinct("properties.$sSubName", self::toJsonSchema($aValue));
                } else if ($mValue instanceof JsonSchemaInterface) {
                    $oResponse->mergeRecursiveDistinct("properties.$sName", $mValue->getJsonSchemaForOpenAPI());

                    if ($mValue instanceof Param && $mValue->isRequired()) {
                        $oResponse->push('required', $sName);
                    }
                } else if ($mValue instanceof OpenApiInterface) {
                    $oResponse->mergeRecursiveDistinct("properties.$sName", $mValue->getOpenAPI());
                } else if ($mValue instanceof Dot) {
                    $aValue = $mValue->all();
                    $oResponse->mergeRecursiveDistinct("properties.$sName", self::toJsonSchema($aValue));
                } else if (is_array($mValue)) {
                    $oResponse->mergeRecursiveDistinct("properties.$sName", self::toJsonSchema($mValue));
                } else {
                    $oResponse->mergeRecursiveDistinct("properties.$sName", $mValue);
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

            $oHeaderJsonParams = new Dot(self::toJsonSchema($this->aHeaderParams));

            foreach($this->aHeaderParams as $sParam => $oParam) {
                if (strpos($sParam, '.') !== false) {
                    continue;
                }

                if ($oParam instanceof Param\_Object) {
                    $aParam = $oParam->OpenAPI($sParam, 'header');
                    $aParam['schema'] = $oHeaderJsonParams->get("properties.{$sParam}");
                    $aParameters[] = $aParam;
                } else {
                    $aParameters[] = $oParam->OpenAPI($sParam, 'header');
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

            // There's a bit of magic going on here.  The issue at hand is that the OpenAPI spec does not allow
            // us to define multiple instances for a status, but it _does_ allow us to define multiple schemas
            // for a status.  This seems to occur more often for multiple error responses (x not found, y not found, etc)
            // So what this does...
            // If the status has just one response, fine, well, and good, generate the response and carry on
            // If the status has multiple responses, collect those responses as `schemas` and then output them as an "anyof" stanza
            // The swagger UI does not handle this properly, but the Redoc UI does, which is correct as this is allowed in the Spec.
            $aMethod['responses'] = [];
            foreach($this->aResponses as $iStatus => $aResponses) {
                if (count($aResponses) > 1) {
                    $aDescription = [];
                    $aSchemas     = [];
                    foreach ($aResponses as $oResponse) {
                        if ($oResponse instanceof OpenApiResponseSchemaInterface) {
                            $sDescription = $iStatus . ' Response';
                            if ($oResponse instanceof ErrorResponseInterface) {
                                $sDescription = $oResponse->getMessage();
                            }

                            $aDescription[] = $sDescription;
                            $aSchemas[]     = $oResponse->getOpenAPI();
                        } else if ($oResponse instanceof OpenApiInterface) {
                            $aSchemas[]     = $oResponse->getOpenAPI();
                        } else {
                            $aSchemas[]     = Reference::create(FullSpec::RESPONSE_DEFAULT)->getOpenAPI();
                        }
                    }

                    if (count($aSchemas) > 1) {
                        $aSchemas = [
                            'oneOf' => $aSchemas
                        ];
                    }

                    if (count($aDescription) == 0) {
                        $sDescription = $iStatus . ' Response';
                    } else if (count($aDescription) == 1) {
                        $sDescription = array_shift($aDescription);
                    } else {
                        $sDescription = implode(', ', array_unique($aDescription));
                    }

                    $aMethod['responses'][$iStatus] = [
                        "description" => $sDescription,
                        "content" => [
                            "application/json" => [
                                'schema' => $aSchemas
                            ]
                        ]
                    ];
                } else {
                    $oResponse = $aResponses[0];
                    if ($oResponse instanceof OpenApiResponseSchemaInterface) {
                        $sDescription = $iStatus . ' Response';
                        if ($oResponse instanceof ErrorResponseInterface) {
                            $sDescription = $oResponse->getMessage();
                        }

                        $aMethod['responses'][$iStatus] = [
                            "description" => $sDescription,
                            "content" => [
                                "application/json" => [
                                    'schema' => $oResponse->getOpenAPI()
                                ]
                            ]
                        ];
                    } else if ($oResponse instanceof OpenApiInterface) {
                        $aMethod['responses'][$iStatus] = $oResponse->getOpenAPI();
                    } else if (is_string($oResponse)) {
                        $aMethod['responses'][$iStatus] = ['description' => $oResponse];
                    } else  {
                        $aMethod['responses'][$iStatus] = Reference::create(FullSpec::RESPONSE_DEFAULT)->getOpenAPI();
                    }
                }

                if ($this->aResponseHeaders) {
                    $aMethod['responses'][$iStatus]['headers'] = $this->aResponseHeaders;
                }
            }

            if (!$this->bSkipDefaultResponses) {

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
                'Deprecated'        => $this->bDeprecated,
                'Path'              => $this->sPath,
                'Public'            => $this->bPublic,
                'HttpMethod'        => $this->sHttpMethod,
                'Method'            => $this->sMethod,
                'Scopes'            => $this->aScopes,
                'PathParams'        => $aPathParams,
                'QueryParams'       => $aQueryParams,
                'PostParams'        => $aPostParams,
                'InHeaders'         => $this->aHeaderParams,
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