<?php
    declare(strict_types = 1);

    namespace Enobrev\API\Middleware;

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

    /**
     * @package Enobrev\API\Middleware
     */
    class MultiEndpointPost implements MiddlewareInterface {
        /** @var RequestHandlerInterface  */
        private $oHandler;

        /** @var Dot */
        private $oData;

        /** @var string[] */
        private $aEndpoints;

        public function __construct(RequestHandlerInterface $oHandler, array $aEndpoints) {
            $this->oHandler   = $oHandler;
            $this->aEndpoints = $aEndpoints;
        }

        /**
         * @param ServerRequestInterface  $oRequest
         * @param RequestHandlerInterface $oHandler
         *
         * @return ResponseInterface
         * @throws Exception\InvalidJmesPath
         * @throws \Exception
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer   = Log::startTimer('Enobrev.Middleware.MultiEndpointPost');
            $oBuilder = ResponseBuilder::get($oRequest);
            $aQuery   = $this->aEndpoints;

            $this->oData = new Dot();

            while (count($aQuery) > 0) {
                /** @var array $aPost */
                $aPost = array_splice($aQuery, 0, 1);
                $sEndpoint = key($aPost);
                try {
                    $sEndpoint = $this->fillEndpointTemplateFromData($sEndpoint);
                    $sEscaped  = $sEndpoint;
                    if (strpos($sEndpoint, '.') !== false) {
                        $sEscaped = '(escaped): ' . str_replace('.', '+', $sEndpoint);
                    }

                    $aQueryParams = [];
                    try {
                        $oUri = new Uri($sEndpoint);
                        parse_str($oUri->getQuery(), $aQueryParams);
                    } catch (InvalidArgumentException $e) {
                        // parse_url does not like colons in urls
                        // https://github.com/guzzle/guzzle/issues/1550
                        // https://bugs.php.net/bug.php?id=71646
                        if (strpos($sEndpoint, '?') === false) {
                            $sEndpoint .= '?';
                        } else {
                            $sEndpoint = 'http://localhost/' . ltrim($sEndpoint, '/');
                        }

                        $oUri = new Uri($sEndpoint);
                        parse_str($oUri->getQuery(), $aQueryParams);
                    }

                    $aPostParams  = isset($aPost[$sEndpoint]) ? $this->fillPostTemplateFromData($aPost[$sEndpoint]) : [];
                    $oSubRequest  = ServerRequestFactory::fromGlobals()->withMethod(Method\POST)
                                                                       ->withUri($oUri)
                                                                       ->withQueryParams($aQueryParams)
                                                                       ->withBody(new Stream('php://memory'))
                                                                       ->withParsedBody($aPostParams);


                    Log::startChildRequest();
                    $oSubResponse = $this->oHandler->handle($oSubRequest);
                    Log::endChildRequest();

                    if ($oSubResponse instanceof JsonResponse) {
                        $aPayload = $oSubResponse->getPayload();
                        foreach($aPayload as $sTable => $aData) {
                            if (strpos($sTable, '_') === 0) {
                                $oBuilder->mergeRecursiveDistinct("_request.multipost.$sEscaped.$sTable", $aData);
                            } else {
                                $this->oData->mergeRecursiveDistinct($sTable, $aData);
                            }
                        }
                    } else {
                        $oBuilder->mergeRecursiveDistinct("_request.multipost.$sEscaped._request.status", $oSubResponse->getStatusCode());
                    }
                } catch (Exception\NoTemplateValues $e) {
                    $sEscaped  = $sEndpoint;
                    if (strpos($sEndpoint, '.') !== false) {
                        $sEscaped = '(escaped): ' . str_replace('.', '+', $sEndpoint);
                    }
                    $oBuilder->set("_request.multipost.$sEscaped", 'Template Unresolved');
                }
            }

            $oBuilder->mergeRecursiveDistinct($this->oData->all());
            $oResponse = ResponseBuilder::update($oRequest, $oBuilder);

            Log::dt($oTimer, ['endpoints' => $this->aEndpoints]);
            return $oHandler->handle($oResponse);
        }

        private const NO_VALUE = '~~NO_VALUE~~';

        /**
         * Turns something like {city_id: {cities.id}} into {city_id: 1} using results of previously called API endpoints
         *
         * @param array $aPost
         * @return array
         * @throws \Exception
         */
        private function fillPostTemplateFromData(array $aPost): array {
            if (count($aPost)) {
                foreach($aPost as $sParam => $mValue) {
                    if (is_array($mValue)) {
                        continue;
                    }

                    $mTemplateValue = $this->getTemplateValue($mValue);
                    if ($mTemplateValue !== self::NO_VALUE) {
                        $aPost[$sParam] = $mTemplateValue;
                    }
                }
            }

            return $aPost;
        }

        /**
         * Turns something like /city_fonts/{cities.id} into /city_fonts/1,2,3 using results of previously called API endpoints
         *
         * @param string $sEndpoint
         *
         * @return mixed
         * @throws Exception\InvalidJmesPath
         * @throws Exception\NoTemplateValues
         */
        private function fillEndpointTemplateFromData(string $sEndpoint) {
            //dbg($sEndpoint);
            $bMatched = preg_match_all('/{[^}]+}/', $sEndpoint, $aMatches);
            if ($bMatched && count($aMatches) > 0) {
                foreach ($aMatches as $sTemplate) {
                    if ($sTemplate !== null) {
                        $mTemplateValue = $this->getTemplateValue($sTemplate);
                        if ($mTemplateValue !== self::NO_VALUE) {
                            $sEndpoint = str_replace($sTemplate, $mTemplateValue, $sEndpoint);
                        }
                    }
                }
            }

            return $sEndpoint;
        }

        /**
         * @param string $sTemplate
         *
         * @return mixed
         * @throws Exception\InvalidJmesPath
         * @throws Exception\NoTemplateValues
         */
        private function getTemplateValue($sTemplate) {
            if (!is_string($sTemplate)) {
                return $sTemplate;
            }

            if (strpos($sTemplate, '{') === 0) {
                $aValues = [];
                $sPrefix = null;
                $sMatch  = trim($sTemplate, '{}');

                if (preg_match('/^([^-]+-)(.+)/', $sMatch, $aMatches)) {
                    [$_, $sPrefix, $sMatch] = $aMatches;
                }

                if (strpos($sMatch, 'jmes:') === 0) {
                    $sExpression = str_replace('jmes:', '', $sMatch);

                    try {
                        $aValues = JmesPath\Env::search($sExpression, $this->oData->all());
                    } catch (RuntimeException $e) {
                        Log::ex('MultiEndpointPost.getTemplateValue.JMESPath.error', $e, [
                            'template'   => $sTemplate,
                            'expression' => $sExpression
                        ]);

                        $aValues = [];
                    }

                    if ($aValues) {
                        if (!is_array($aValues)) {
                            $aValues = [$aValues];
                        } else if (count($aValues) && is_array($aValues[0])) { // cannot work with a multi-array
                            Log::ex('MultiEndpointPost.getTemplateValue.JMESPath', $e, [
                                'template'   => $sTemplate,
                                'expression' => $sExpression
                            ]);

                            throw new Exception\InvalidJmesPath('JmesPath Needs to return a flat array, this was a multidimensional array.  Consider the flatten projection operator []');
                        }
                    }

                    Log::d('MultiEndpointPost.getTemplateValue.JMESPath', [
                        'template'   => $sTemplate,
                        'expression' => $sExpression
                    ]);
                } else {
                    $sPrefix = null;
                    if (preg_match('/^([^-]+-)(.+)/', $sMatch, $aMatches)) {
                        [$_, $sPrefix, $sMatch] = $aMatches;
                    }

                    $aMatch = explode('.', $sMatch);
                    if (count($aMatch) === 2) {
                        [$sTable, $sField] = $aMatch;

                        if ($this->oData->has($sTable)) {
                            $aValues = [];
                            foreach ($this->oData->get($sTable) as $aTable) {
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
                                } else if ($this->oData->has("$sTable.$sField")) {
                                    // Single-Record response (like /me)
                                    $aValues[] = $this->oData->get("$sTable.$sField");
                                } else {
                                    Log::d('MultiEndpointPost.getTemplateValue.SegmentNotFound', [
                                        'field' => $sField,
                                        'template' => $sTemplate
                                    ]);
                                }
                            }

                            Log::d('MultiEndpointPost.getTemplateValue.TableField', [
                                'template' => $sTemplate
                            ]);
                        }
                    }
                }

                if ($sPrefix) {
                    foreach($aValues as &$sValue) {
                        $sValue = $sPrefix . $sValue;
                    }
                    unset($sValue);
                }

                Log::d('MultiEndpointQuery.getTemplateValue', [
                    'prefix' => $sPrefix,
                    'values' => json_encode($aValues)
                ]);

                if (count($aValues)) {
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
    }