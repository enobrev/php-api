<?php
    namespace Enobrev\API;

    use Adbar\Dot;
    use Middlewares\HttpErrorException;

    use Enobrev\API\FullSpec\ComponentInterface;
    use Enobrev\API\FullSpec\Component\ParamSchema;
    use Enobrev\API\FullSpec\Component\Reference;
    use Enobrev\API\FullSpec\Component\Response;
    use Enobrev\API\FullSpec\Component\Request;
    use Enobrev\API\FullSpec\Component\Schema;
    use Enobrev\API\HTTP;
    use Enobrev\API\Spec\ErrorResponseInterface;
    use Enobrev\API\Spec\ProcessErrorResponse;
    use Enobrev\ORM\Field;
    use Enobrev\ORM\Table;

    use function Enobrev\array_not_associative;
    use ReflectionException;

    class Spec {
        private const SKIP_PRIMARY = 1024;

        /** @var string */
        private $sSummary;

        /** @var string */
        private $sDescription;

        /** @var boolean */
        private $bSkipDefaultResponses = false;

        /** @var boolean */
        private $bDeprecated = false;

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

        /** @var callable */
        private $oPostBodySchemaSelector;

        /** @var array */
        private $aResponseHeaders = [];

        /** @var array */
        private $aCodeSamples = [];

        /** @var string[] */
        private $aTags = [];

        /** @var array */
        private $aResponses = [];

        public static function create(): Spec {
            return new self();
        }

        public function getPath():string {
            return $this->sPath;
        }

        public function getPathForDocs():string {
            return str_replace(['.', '[/]'], ['_DOT_', '/'], $this->sPath);
        }

        public function getSummary():string {
            return $this->sSummary;
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

        /**
         * @param int $iStatus
         *
         * @return array|mixed|string|null
         * @throws Exception\InvalidDescription
         * @throws Exception\InvalidStatus
         */
        public function getResponseDescription(int $iStatus) {
            $mResponse = $this->aResponses[$iStatus] ?? null;

            if (!$mResponse) {
                throw new Exception\InvalidStatus('Invalid Status');
            }

            if ($mResponse instanceof Response) {
                return $mResponse->getDescription();
            }

            if (is_string($mResponse)) {
                return $mResponse;
            }

            if (is_array($mResponse)) {
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

            throw new Exception\InvalidDescription('Not Sure What the Response Description Is');
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

            return count(array_intersect($aScopes, $this->aScopes)) === count($aScopes);
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

        /**
         * @return Param[]
         * @throws ReflectionException
         */
        public function resolvePostParams(): array {
            if ($this->oPostBodyReference) {
                if ($this->oPostBodyReference instanceof Reference) {
                    // FIXME: This _may_ be a big fat hack
                    $oFullSpec = FullSpec::getFromCache();
                    $oComponent = $oFullSpec->followTheYellowBrickRoad($this->oPostBodyReference);
                } else {
                    $oComponent = $this->oPostBodyReference;
                }

                $aParams = [];
                if ($oComponent instanceof ParamSchema) {
                    $oParam = $oComponent->getParam();
                    $aParams = $oParam->getItems();
                } else if ($oComponent instanceof OpenApiInterface) {
                    $aSpec = $oComponent->getOpenAPI();
                    if (isset($aSpec['properties'])) {
                        foreach ($aSpec['properties'] as $sParam => $aProperty) {
                            $aParams[$sParam] = Param::createFromJsonSchema($aProperty);
                        }
                    }
                }

                if (count($this->aPostParams)) {
                    $aParams = array_merge($aParams, $this->aPostParams);
                }
            } else {
                $aParams = $this->aPostParams;
            }

            return $aParams;
        }

        /**
         * @return Param[]
         */
        public function getHeaderParams(): array {
            return $this->aHeaderParams;
        }

        public function isPublic():bool {
            return $this->bPublic;
        }

        public function isDeprecated():bool {
            return $this->bDeprecated;
        }

        public function pathParamsToJsonSchema():array {
            return Param\_Object::create()->items($this->aPathParams)->getJsonSchema();
        }

        public function queryParamsToJsonSchema():array {
            return Param\_Object::create()->items($this->aQueryParams)->getJsonSchema();
        }

        public function hasAPostBodyOneOf(): bool {
            if ($this->oPostBodyReference instanceof Request) {
                $oPost = $this->oPostBodyReference->getPost();
                return $oPost instanceof Schema && $oPost->isOneOf();
            }

            return false;
        }

        public function getPostBodySchemas() {
            if ($this->oPostBodyReference instanceof Request) {
                $oPost = $this->oPostBodyReference->getPost();

                if ($oPost instanceof Schema && $oPost->isOneOf()) {
                    return $oPost->getSchema();
                }
            }

            return null;
        }

        public function hasAPostBodyReference(): bool {
            return $this->oPostBodyReference instanceof Reference;
        }

        /**
         * @return array
         * @throws ReflectionException
         */
        public function postParamsToJsonSchema():array {
            if ($this->hasAPostBodyReference()) {
                if ($this->oPostBodyReference instanceof Reference) {
                    // FIXME: This _may_ be a big fat hack
                    $oFullSpec = FullSpec::getFromCache();
                    $oComponent = $oFullSpec->followTheYellowBrickRoad($this->oPostBodyReference);
                } else {
                    $oComponent = $this->oPostBodyReference;
                }

                if ($oComponent instanceof ParamSchema) {
                    return $oComponent->getParam()->getJsonSchema();
                }

                if ($oComponent instanceof Request) {
                    $oJSON = $oComponent->getJson();
                    if ($oJSON instanceof ParamSchema) {
                        return $oJSON->getParam()->getJsonSchema();
                    }

                    return $oJSON->getOpenAPI();
                }

                $aPostParams = $this->resolvePostParams();
            } else {
                $aPostParams = $this->aPostParams;
            }

            return Param\_Object::create()->items($aPostParams)->getJsonSchema();
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
         * @throws InvalidScope
         */
        public function scopes(array $aScopes):self {
            if (!array_not_associative($aScopes)) {
                throw new InvalidScope('Please define Scopes as a non-Associative Array');
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

        public function postBodySchemaSelector(callable $fSelector): Spec {
            $oClone = clone $this;
            $oClone->oPostBodySchemaSelector = $fSelector;
            return $oClone;
        }

        public function hasPostBodySchemaSelector(): bool {
            return is_callable($this->oPostBodySchemaSelector);
        }

        public function getSchemaFromSelector($oProperties) {
            $fSelector =  $this->oPostBodySchemaSelector;
            return $fSelector($this->getPostBodySchemas(), $oProperties);
        }

        public function postParams(array $aParams):self {
            $oClone = clone $this;
            $oClone->aPostParams = $aParams;
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

        public function responseFromException(HttpErrorException $oException): Spec {
            $oResponse = ProcessErrorResponse::createFromException($oException);
            return $this->response($oResponse->getCode(), $oResponse);
        }

        public function tags(array $aTags):self {
            $oClone = clone $this;
            /** @noinspection AdditionOperationOnArraysInspection */
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

        /**
         * @param Table $oTable
         * @param int   $iOptions
         * @param array $aExclude
         *
         * @return array
         * @throws Exception\InvalidDataMapPath
         * @throws Exception\MissingDataMapDefinition
         */
        public static function tableToJsonSchema(Table $oTable, int $iOptions = 0, array $aExclude = []): array {
            return self::toJsonSchema(self::tableToParams($oTable, $iOptions, $aExclude));
        }

        /**
         * @param Table $oTable
         * @param array $aExclude
         * @param int   $iOptions
         * @param bool  $bAdditionalProperties
         *
         * @return Param\_Object
         * @throws Exception\InvalidDataMapPath
         * @throws Exception\MissingDataMapDefinition
         */
        public static function tableToParam(Table $oTable, array $aExclude = [], int $iOptions = 0, $bAdditionalProperties = false): Param\_Object {
            return Param\_Object::create()->items(self::tableToParams($oTable, $iOptions, $aExclude))->additionalProperties($bAdditionalProperties);
        }

        /**
         * @param Table $oTable
         * @param int   $iOptions
         * @param array $aExclude
         *
         * @return Param[]
         * @throws Exception\InvalidDataMapPath
         * @throws Exception\MissingDataMapDefinition
         */
        public static function tableToParams(Table $oTable, int $iOptions = 0, array $aExclude = []): array {
            $aDefinitions = [];
            $aFields = $oTable->getColumnsWithFields();

            foreach($aFields as $oField) {
                if ($iOptions & self::SKIP_PRIMARY && $oField->isPrimary()) {
                    continue;
                }

                if (in_array($oField->sColumn, $aExclude, true)) {
                    continue;
                }

                $sField = DataMap::getPublicName($oTable, $oField->sColumn);
                if (!$sField) {
                    continue;
                }

                $oParam = self::fieldToParam($oField, $iOptions);
                if ($oParam instanceof Param) {
                    $aDefinitions[$sField] = $oParam;
                }
            }

            return $aDefinitions;
        }

        /**
         * @param Field $oField
         * @param int $iOptions
         * @param bool $bIncludeDefault
         * @return Param
         */
        public static function fieldToParam(Field $oField, int $iOptions = 0, $bIncludeDefault = false): Param {
            switch(true) {
                default:
                case $oField instanceof Field\Text:    $oParam = Param\_String::create();  break;
                case $oField instanceof Field\Boolean: $oParam = Param\_Boolean::create(); break;
                case $oField instanceof Field\Integer: $oParam = Param\_Integer::create(); break;
                case $oField instanceof Field\Number:  $oParam = Param\_Number::create();  break;
            }

            switch(true) {
                case $oField instanceof Field\Enum:
                    $oParam = $oParam->enum($oField->aValues);
                    break;

                case $oField instanceof Field\TextNullable:
                    $oParam = $oParam->nullable();
                    break;

                case $oField instanceof Field\Time:
                    // No Formatting
                    break;

                case $oField instanceof Field\DateTime:
                    $oParam = $oParam->format('date-time');
                    break;

                case $oField instanceof Field\Date:
                    $oParam = $oParam->format('date');
                    break;
            }

            if ($oField instanceof Field\Integer) {
                switch(PHP_INT_SIZE) {
                    case 4: $oParam = $oParam->format('int32'); break;
                    case 8: $oParam = $oParam->format('int64'); break;
                }
            }

            if ($oField->sDefault === null) {
                $oParam = $oParam->nullable();
            }

            if (stripos($oField->sColumn, 'password') !== false) {
                $oParam = $oParam->format('password');
            }

            if ($bIncludeDefault && $oField->hasDefault()) {
                // Initially the default was always included, but this was a problem.  Let's say you generate params for a whole table
                // that are to be used as postParams for and API endpoint.  Say, a default value of 0 for age.  Now in a future
                // POST to that endpoint, if age was not set, then the parameter will be coerced into its default of 0, which will
                // change the record to age 0
                if ($oField instanceof Field\Boolean) {
                    $oParam = $oParam->default((bool) $oField->sDefault);
                } else {
                    $oParam = $oParam->default($oField->sDefault);
                }
            }

            if ($iOptions & Param::REQUIRED) {
                $oParam = $oParam->required();
            }

            if ($iOptions & Param::NULLABLE) {
                $oParam = $oParam->nullable();
            }

            if ($iOptions & Param::DEPRECATED) {
                $oParam = $oParam->deprecated();
            }

            return $oParam;
        }

        /**
         * @param array $aArray
         * @param bool $bAdditionalProperties
         * @return array
         */
        public static function toJsonSchema(array $aArray, $bAdditionalProperties = false): array {
            if (isset($aArray['type']) && in_array($aArray['type'], ['object', 'array', 'integer', 'number', 'boolean', 'string'])) {
                // this is likely already a jsonschema
                return $aArray;
            }

            $oResponse = new Dot([
                'type'                  => 'object',
                'additionalProperties'  => $bAdditionalProperties,
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

        private function getOperationId() {
            $sPath = $this->sPath;
            $sPath = $this->sHttpMethod . $sPath;
            $sPath = preg_replace('~^/~',        '',    $sPath);
            $sPath = preg_replace('/{([^}]+)}/', ':$1', $sPath);
            $sPath = str_replace('[/]',           '',        $sPath);
            return $sPath;
        }

        public function generateOpenAPI(): array {
            $aMethod = [
                'operationId'   => $this->getOperationId(),
                'summary'       => $this->sSummary ?? $this->sPath,
                'description'   => $this->sDescription ?? $this->sSummary ?? $this->sPath,
                'tags'          => $this->aTags
            ];

            if (!$this->bPublic && count($this->aScopes)) {
                $aMethod['security'] = [['OAuth2' => $this->aScopes]];
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
                    $aParam = $oParam->OpenAPI($sParam);
                    $aParam['schema'] = $oQueryJsonParams->get("properties.{$sParam}");
                    $aParameters[] = $aParam;
                } else {
                    $aParameters[] = $oParam->OpenAPI($sParam);
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
                        $aPost[$sParam] = $oParam->getJsonSchemaForOpenAPI();

                        if ($oParam->isRequired()) {
                            $aPost[$sParam]['required'] = true;
                        }
                    }
                }

                if (count($aPost)) {
                    $aMethod['requestBody'] = [
                        'content' => [
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

                    if (count($aDescription) === 0) {
                        $sDescription = $iStatus . ' Response';
                    } else if (count($aDescription) === 1) {
                        $sDescription = array_shift($aDescription);
                    } else {
                        $sDescription = implode(', ', array_unique($aDescription));
                    }

                    $aMethod['responses'][$iStatus] = [
                        'description' => $sDescription,
                        'content'     => [
                            'application/json' => [
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
                            'description' => $sDescription,
                            'content'     => [
                                'application/json' => [
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

                if (!isset($aMethod['responses'][HTTP\BAD_REQUEST]) && count($aParameters)) {
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

        /**
         * @return array
         * @throws ReflectionException
         */
        public function toArray(): array {
            $aPathParams = [];
            foreach($this->resolvePostParams() as $sParam => $oParam) {
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
                'summary'           => $this->sSummary,
                'description'       => $this->sDescription,
                'deprecated'        => $this->bDeprecated,
                'path'              => $this->sPath,
                'public'            => $this->bPublic,
                'httpMethod'        => $this->sHttpMethod,
                'method'            => $this->sMethod,
                'scopes'            => $this->aScopes,
                'pathParams'        => $aPathParams,
                'queryParams'       => $aQueryParams,
                'postParams'        => $aPostParams,
                'headerParams'      => $this->aHeaderParams,
                'codeSamples'       => $this->aCodeSamples,
                'responseHeaders'   => $this->aResponseHeaders,
                'responses'         => $this->aResponses,
                'tags'              => $this->aTags
            ];
        }

        /**
         * @return false|string
         * @throws ReflectionException
         */
        public function toJson() {
            return json_encode($this->toArray());
        }
    }