<?php
    declare(strict_types = 1);

    namespace Enobrev\API\Middleware;

    use Adbar\Dot;
    use JmesPath;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use Zend\Diactoros\Response\JsonResponse;
    use Zend\Diactoros\ServerRequestFactory;
    use Zend\Diactoros\Stream;
    use Zend\Diactoros\Uri;

    use Enobrev\API\Exception;
    use Enobrev\API\Method;
    use Enobrev\Log;

    use function Enobrev\dbg;

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
         * @param ServerRequestInterface $oSubRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         * @throws Exception\InvalidJmesPath
         * @throws Exception\InvalidSegmentVariable
         * @throws Exception\NoTemplateValues
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer   = Log::startTimer('Enobrev.Middleware.MultiEndpointPost');
            $oBuilder = ResponseBuilder::get($oRequest);
            $aQuery   = $this->aEndpoints;

            $this->oData = new Dot();

            while (count($aQuery) > 0) {
                /** @var array $aPost */
                $aPost = array_splice($aQuery, 0, 1);
                try {
                    $sEndpoint = key($aPost);
                    $sEndpoint = $this->fillEndpointTemplateFromData($sEndpoint);
                    $sEscaped  = $sEndpoint;
                    if (strpos($sEndpoint, '.') !== false) {
                        $sEscaped = '(escaped): ' . str_replace(".", "+", $sEndpoint);
                    }

                    $oUri         = new Uri($sEndpoint);
                    $aQueryParams = [];
                    parse_str($oUri->getQuery(), $aQueryParams);

                    $aPostParams  = isset($aPost[$sEndpoint]) ? self::fillPostTemplateFromData($aPost[$sEndpoint]) : [];

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
                        $sEscaped = '(escaped): ' . str_replace(".", "+", $sEndpoint);
                    }
                    $oBuilder->set("_request.multipost.$sEscaped", 'Template Unresolved');
                }
            }

            $oBuilder->mergeRecursiveDistinct($this->oData->all());
            $oResponse = ResponseBuilder::update($oRequest, $oBuilder);

            Log::dt($oTimer, ['endpoints' => $this->aEndpoints]);
            return $oHandler->handle($oResponse);
        }

        const NO_VALUE = '~~NO_VALUE~~';

        /**
         * Turns something like {city_id: {cities.id}} into {city_id: 1} using results of previously called API endpoints
         *
         * @param array $aPost
         * @return array
         * @throws \Exception
         */
        private function fillPostTemplateFromData(array $aPost) {
            if (count($aPost)) {
                foreach($aPost as $sParam => $mValue) {
                    if (is_array($mValue)) {
                        continue;
                    }

                    $mTemplateValue = self::getTemplateValue($mValue);
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
         * @param $sEndpoint
         * @return string
         * @throws Exception\InvalidJmesPath
         * @throws Exception\InvalidSegmentVariable
         * @throws Exception\NoTemplateValues
         */
        private function fillEndpointTemplateFromData(string $sEndpoint) {
            //dbg($sEndpoint);
            $bMatched = preg_match_all('/{[^}]+}/', $sEndpoint, $aMatches);
            if ($bMatched && count($aMatches) > 0) {
                $aTemplates = $aMatches[0];
                foreach ($aTemplates as $sTemplate) {
                    $mTemplateValue = self::getTemplateValue($sTemplate);
                    if ($mTemplateValue !== self::NO_VALUE) {
                        $sEndpoint = str_replace($sTemplate, $mTemplateValue, $sEndpoint);
                    }
                }
            }

            return $sEndpoint;
        }

        /**
         * @param string $sTemplate
         * @return string
         * @throws Exception\InvalidJmesPath
         * @throws Exception\InvalidSegmentVariable
         * @throws Exception\NoTemplateValues
         */
        private function getTemplateValue(string $sTemplate) {
            if (strpos($sTemplate, '{') === 0) {
                $aValues = [];
                $sPrefix = null;
                $sMatch  = trim($sTemplate, "{}");

                if (preg_match('/^([^-]+-)(.+)/', $sMatch, $aMatches)) {
                    $sPrefix = $aMatches[1];
                    $sMatch  = $aMatches[2];
                }

                if (strpos($sMatch, 'jmes:') === 0) {
                    $sExpression = str_replace('jmes:', '', $sMatch);

                    try {
                        $aValues = JmesPath\Env::search($sExpression, $this->oData->all());
                    } catch (\RuntimeException $e) {
                        Log::e('MultiEndpointPost.getTemplateValue.JMESPath.error', [
                            'template'   => $sTemplate,
                            'expression' => $sExpression,
                            'error'      => $e
                        ]);

                        $aValues = [];
                    }

                    if ($aValues) {
                        if (!is_array($aValues)) {
                            $aValues = [$aValues];
                        } else if (count($aValues) && is_array($aValues[0])) { // cannot work with a multi-array
                            Log::e('MultiEndpointPost.getTemplateValue.JMESPath', [
                                'template'   => $sTemplate,
                                'expression' => $sExpression,
                                'values'     => $aValues
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
                        $sPrefix = $aMatches[1];
                        $sMatch  = $aMatches[2];
                    }

                    $aMatch = explode('.', $sMatch);
                    if (count($aMatch) == 2) {
                        $sTable = $aMatch[0];
                        $sField = $aMatch[1];

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
                                    throw new Exception\InvalidSegmentVariable('Invalid Segment Variable ' . $sField . ' in ' . $sTemplate);
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
                }

                Log::d('MultiEndpointQuery.getTemplateValue', [
                    'prefix' => $sPrefix,
                    'values' => json_encode($aValues)
                ]);

                if (count($aValues)) {
                    $aUniqueValues = array_unique(array_filter($aValues));
                    if (count($aValues) > 0 && count($aUniqueValues) == 0) {
                        throw new Exception\NoTemplateValues();
                    }

                    return implode(',', $aUniqueValues);
                }
            }

            return $sTemplate;
        }
    }