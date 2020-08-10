<?php
    declare(strict_types = 1);

    namespace Enobrev\API\Middleware;

    use Countable;
    use Flow\JSONPath\JSONPath;
    use Laminas\Diactoros\Exception\InvalidArgumentException;
    use RuntimeException;

    use Adbar\Dot;
    use JmesPath;
    use Laminas\Diactoros\Response\JsonResponse;
    use Laminas\Diactoros\ServerRequestFactory;
    use Laminas\Diactoros\Stream;
    use Laminas\Diactoros\Uri;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\Exception;
    use Enobrev\API\Method;
    use Enobrev\Log;
    use function Enobrev\dbg;

    /**
     * @package Enobrev\API\Middleware
     */
    class MultiEndpointQuery implements MiddlewareInterface {
        /** @var RequestHandlerInterface  */
        private $oHandler;

        /** @var Dot */
        private $oData;

        /** @var string[] */
        private $aEndpoints;

        public function __construct(RequestHandlerInterface $oHandler, array $aEndpoints) {
            $this->oHandler = $oHandler;
            $this->aEndpoints = $aEndpoints;
        }

        /**
         * @param ServerRequestInterface  $oRequest
         * @param RequestHandlerInterface $oHandler
         *
         * @return ResponseInterface
         * @throws Exception\InvalidJmesPath
         * @throws Exception\InvalidJsonPath
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer   = Log::startTimer('Enobrev.Middleware.MultiEndpointQuery');
            $oBuilder = ResponseBuilder::get($oRequest);
            $aQuery   = $this->aEndpoints;

            Log::d('Enobrev.Middleware.MultiEndpointQuery', ['query' => $aQuery]);

            $this->oData = new Dot();

            while (count($aQuery) > 0) {
                /** @var string $sEndpoint */
                $sEndpoint = array_shift($aQuery);
                try {
                    $sEndpoint = $this->fillEndpointTemplateFromData($sEndpoint);
                    $sEscaped  = $sEndpoint;
                    if (strpos($sEndpoint, '.') !== false) {
                        $sEscaped = '(escaped): ' . str_replace('.', '+', $sEndpoint);
                    }


                    try {
                        $oUri = new Uri($sEndpoint);
                    } catch (InvalidArgumentException $e) {
                        // parse_url does not like colons in urls
                        // https://github.com/guzzle/guzzle/issues/1550
                        // https://bugs.php.net/bug.php?id=71646
                        $sEndpoint = 'http://localhost/' . ltrim($sEndpoint, '/');
                        $oUri = new Uri($sEndpoint);
                    }

                    $aQueryParams = [];
                    parse_str($oUri->getQuery(), $aQueryParams);

                    $oSubRequest  = ServerRequestFactory::fromGlobals()->withMethod(Method\GET)
                                                                       ->withUri($oUri)
                                                                       ->withQueryParams($aQueryParams)
                                                                       ->withBody(new Stream('php://memory'))
                                                                       ->withParsedBody(null);


                    Log::startChildRequest();
                    $oSubResponse = $this->oHandler->handle($oSubRequest);
                    Log::endChildRequest();

                    if ($oSubResponse instanceof JsonResponse) {
                        $aPayload = $oSubResponse->getPayload();
                        foreach($aPayload as $sTable => $aData) {
                            if (strpos($sTable, '_') === 0) {
                                $oBuilder->mergeRecursiveDistinct("_request.multiquery.$sEscaped.$sTable", $aData);
                            } else {
                                $this->oData->mergeRecursiveDistinct($sTable, $aData);
                            }
                        }
                    } else {
                        $oBuilder->mergeRecursiveDistinct("_request.multiquery.$sEscaped._request.status", $oSubResponse->getStatusCode());
                    }
                } catch (Exception\NoTemplateValues $e) {
                    $sEscaped  = $sEndpoint;
                    if (strpos($sEndpoint, '.') !== false) {
                        $sEscaped = '(escaped): ' . str_replace('.', '+', $sEndpoint);
                    }
                    $oBuilder->set("_request.multiquery.$sEscaped", 'Template Unresolved');
                } catch (Exception\InvalidTemplateResponse $e) {
                    $sEscaped  = $sEndpoint;
                    if (strpos($sEndpoint, '.') !== false) {
                        $sEscaped = '(escaped): ' . str_replace('.', '+', $sEndpoint);
                    }
                    $oBuilder->set("_request.multiquery.$sEscaped", 'Template References Data That Cannot Be Flattened');
                }
            }

            $oBuilder->mergeRecursiveDistinct($this->oData->all());
            $oResponse = ResponseBuilder::update($oRequest, $oBuilder);

            Log::dt($oTimer, ['endpoints' => $this->aEndpoints]);
            return $oHandler->handle($oResponse);
        }

        /**
         * This is a stripped down version of the process method.  This class is not easy to test without mocking
         * up everything.  I just need to test that the templating works, not that the API it wraps works
         *
         * @param Dot   $oData
         * @param array $aEndpoints
         *
         * @return Uri[]
         * @throws Exception\InvalidJmesPath
         * @throws Exception\InvalidJsonPath
         * @throws Exception\NoTemplateValues
         */
        public function testTemplates(Dot $oData, array $aEndpoints) {
            $this->oData = $oData;
            $aRequests = [];
            while (count($aEndpoints) > 0) {
                /** @var string $sEndpoint */
                $sEndpoint = array_shift($aEndpoints);
                $sEndpoint = $this->fillEndpointTemplateFromData($sEndpoint);

                try {
                    $oUri = new Uri($sEndpoint);
                } catch (InvalidArgumentException $e) {
                    // parse_url does not like colons in urls
                    // https://github.com/guzzle/guzzle/issues/1550
                    // https://bugs.php.net/bug.php?id=71646
                    $sEndpoint = 'http://localhost/' . ltrim($sEndpoint, '/');
                    $oUri = new Uri($sEndpoint);
                }

                $aRequests[] = $oUri;
            }
            return $aRequests;
        }

        private const NO_VALUE = '~~NO_VALUE~~';

        /**
         * Turns something like /city_fonts/{cities.id} into /city_fonts/1,2,3 using results of previously called API endpoints
         *
         * @param string $sEndpoint
         *
         * @return string
         * @throws Exception\InvalidJmesPath
         * @throws Exception\InvalidJsonPath
         * @throws Exception\NoTemplateValues
         */
        private function fillEndpointTemplateFromData(string $sEndpoint): string {
            //dbg($sEndpoint);
            $bMatched = preg_match_all('/{[^}]+}/', $sEndpoint, $aMatches);
            if ($bMatched && count($aMatches) > 0) {
                $aTemplates = $aMatches[0];
                foreach ($aTemplates as $sTemplate) {
                    $mTemplateValue = $this->getTemplateValue($sTemplate);
                    if ($mTemplateValue !== self::NO_VALUE) {
                        $sEndpoint = str_replace($sTemplate, $mTemplateValue, $sEndpoint);
                    }
                }
            }

            return $sEndpoint;
        }

        /**
         * @param string $sTemplate
         *
         * @return string
         * @throws Exception\InvalidJmesPath
         * @throws Exception\InvalidJsonPath
         * @throws Exception\NoTemplateValues
         */
        private function getTemplateValue(string $sTemplate): string {
            if (strpos($sTemplate, '{') === 0) {
                $aValues = [];
                $sPrefix = null;
                $sMatch  = trim($sTemplate, '{}');

                if (preg_match('/^([^-]+-)(.+)/', $sMatch, $aMatches)) {
                    [$_, $sPrefix, $sMatch] = $aMatches;
                }

                if (strpos($sMatch, 'jmes:') === 0) {
                    $aValues = $this->useJMES($sMatch, $sTemplate);
                } else if (strpos($sMatch, 'jsonpath:') === 0) {
                    $aValues = $this->useJSONPath($sMatch, $sTemplate);
                } else {
                    $aMatch = explode('.', $sMatch);
                    if (count($aMatch) > 1) {
                        $sField = array_pop($aMatch);
                        $sPath  = implode('.', $aMatch);

                        // {table.field} => table.{id}.field
                        // table.{id}.[{field},{field}]
                        // table.{id}.field[]

                        if ($this->oData->has($sPath)) {
                            $aValues = [];
                            foreach ($this->oData->get($sPath) as $aTable) {
                                if (is_array($aTable) && array_key_exists($sField, $aTable)) {
                                    if (is_array($aTable[$sField])) {
                                        /*
                                         Handles consolidated arrays of ids.  Example:

                                            "table": {
                                                "{id}": {
                                                    "column": [
                                                        "id1"
                                                    ],
                                                    "column2": [
                                                        "id2",
                                                        "id3",
                                                        "id4"
                                                    ]
                                                }
                                            }

                                            - If you then query like so:

                                            "/table/",
                                            "/reference_table/{table.column}",
                                            "/reference_table/{table.column2}"

                                            table.column and table.column2 are arrays of Ids in this instance and need to be handled thusly
                                        */
                                        $aValues = array_merge($aValues, $aTable[$sField]);
                                    } else {
                                        $aValues[] = $aTable[$sField];
                                    }
                                } else if ($this->oData->has("$sPath.$sField")) {
                                    // Single-Record response (like /me)
                                    $aValues[] = $this->oData->get("$sPath.$sField");
                                } else {
                                    Log::d('MultiEndpointQuery.getTemplateValue', [
                                        'state'     => 'SegmentNotFound',
                                        'field'     => $sField,
                                        'template'  => $sTemplate
                                    ]);
                                }
                            }

                            Log::d('MultiEndpointQuery.getTemplateValue', [
                                'state'    => 'TableField',
                                'template' => $sTemplate
                            ]);
                        }
                    }
                }

                foreach($aValues as $sValue) {
                    if (is_array($sValue)) {
                        throw new Exception\InvalidTemplateResponse('Path Tempalates MUST refernce values that can be flattened.  This template references a value that cannot be easily flattened');
                    }
                }

                if ($sPrefix) {
                    Log::d('MultiEndpointQuery.getTemplateValue', [
                        'state'  => 'Prefix',
                        'prefix' => $sPrefix,
                        'values' => json_encode($aValues)
                    ]);

                    foreach($aValues as &$sValue) {
                        $sValue = $sPrefix . $sValue;
                    }
                    unset($sValue);
                }

                Log::d('MultiEndpointQuery.getTemplateValue', [
                    'state'  => 'Data',
                    'prefix' => $sPrefix,
                    'values' => json_encode($aValues)
                ]);

                if ((is_array($aValues) || $aValues instanceof Countable) && count($aValues)) { // is_countable is 7.3+
                    $aUniqueValues = array_unique(array_filter($aValues));
                    if (count($aValues) > 0 && count($aUniqueValues) === 0) {
                        throw new Exception\NoTemplateValues();
                    }

                    return implode(',', $aUniqueValues);
                }

                throw new Exception\NoTemplateValues();
            }

            return $sTemplate;
        }

        /**
         * @param string $sMatch
         * @param string $sTemplate
         * @deprecated
         *
         * @return array|array[]|mixed|null
         * @throws Exception\InvalidJmesPath
         */
        private function useJMES(string $sMatch, string $sTemplate) {
            $sExpression = str_replace('jmes:', '', $sMatch);

            try {
                $aValues = JmesPath\Env::search($sExpression, $this->oData->all());
            } catch (RuntimeException $e) {
                Log::ex('MultiEndpointQuery.getTemplateValue.JMESPath', $e, [
                    'state'      => 'JMESPath.Error',
                    'template'   => $sTemplate,
                    'expression' => $sExpression
                ]);

                $aValues = [];
            }

            if ($aValues) {
                if (!is_array($aValues)) {
                    $aValues = [$aValues];
                } else {
                    if (count($aValues) && is_array($aValues[0])) { // cannot work with a multi-array
                        Log::e('MultiEndpointQuery.getTemplateValue', [
                            'state'      => 'JMESPath.MultiArray',
                            'template'   => $sTemplate,
                            'expression' => $sExpression,
                            'values'     => json_encode($aValues)
                        ]);

                        throw new Exception\InvalidJmesPath('JmesPath Needs to return a flat array, this was a multidimensional array.  Consider the flatten projection operator []');
                    }
                }
            }

            Log::d('MultiEndpointQuery.getTemplateValue.JMESPath', [
                'state'      => 'Complete',
                'template'   => $sTemplate,
                'expression' => $sExpression
            ]);

            return $aValues;
        }

        /**
         * @param string $sMatch
         * @param string $sTemplate
         *
         * @return array|mixed
         * @throws Exception\InvalidJsonPath
         */
        private function useJSONPath(string $sMatch, string $sTemplate) {
            $sExpression = str_replace('jsonpath:', '', $sMatch);
            $aValues = [];

            try {
                $oValues = (new JSONPath($this->oData->all()))->find($sExpression);

                if ($oValues) {
                    $aValues = $oValues->data();
                }
            } catch (Exception $e) {
                Log::ex('MultiEndpointQuery.getTemplateValue.JSONPath', $e, [
                    'state'      => 'JSONPath.Error',
                    'template'   => $sTemplate,
                    'expression' => $sExpression
                ]);
            }

            if ($aValues && count($aValues) && is_array($aValues[0])) { // cannot work with a multi-array
                Log::e('MultiEndpointQuery.getTemplateValue.JSONPath', [
                    'state'      => 'JSONPath.MultiArray',
                    'template'   => $sTemplate,
                    'expression' => $sExpression,
                    'values'     => json_encode($aValues)
                ]);

                throw new Exception\InvalidJsonPath('JSONPath Needs to return a flat array, this was a multidimensional array.  Consider the flatten projection operator []');
            }

            Log::d('MultiEndpointQuery.getTemplateValue.JSONPath', [
                'state'      => 'Complete',
                'template'   => $sTemplate,
                'expression' => $sExpression
            ]);

            return $aValues;
        }
    }