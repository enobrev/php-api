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
    use Zend\Diactoros\Uri;

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

        /** @var string[[ */
        private $aEndpoints;

        public function __construct(RequestHandlerInterface $oHandler, array $aEndpoints) {
            $this->oHandler = $oHandler;
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
            $oBuilder = ResponseBuilder::get($oRequest);
            $aQuery   = $this->aEndpoints;

            $this->oData = new Dot();

            while (count($aQuery) > 0) {
                /** @var string $sEndpoint */
                $sEndpoint = array_shift($aQuery);
                $sEndpoint = $this->fillEndpointTemplateFromData($sEndpoint);
                $sEscaped  = $sEndpoint;
                if (strpos($sEndpoint, '.') !== false) {
                    $sEscaped = '(escaped): ' . str_replace(".", "+", $sEndpoint);
                }

                $oUri         = new Uri($sEndpoint);
                $aQueryParams = [];
                parse_str($oUri->getQuery(), $aQueryParams);
                $oSubRequest  = ServerRequestFactory::fromGlobals()->withMethod(Method\GET)
                                                                   ->withUri($oUri)
                                                                   ->withQueryParams($aQueryParams)
                                                                   ->withParsedBody(null);

                $oSubResponse = $this->oHandler->handle($oSubRequest);
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
                    $oBuilder->mergeRecursiveDistinct("_request.multiquery.$sEscaped._response.status", $oSubResponse->getStatusCode());
                }
            }

            $oBuilder->mergeRecursiveDistinct($this->oData->all());
            return $oHandler->handle(ResponseBuilder::update($oRequest, $oBuilder));
        }

        const NO_VALUE = '~~NO_VALUE~~';

        /**
         * Turns something like /city_fonts/{cities.id} into /city_fonts/1,2,3 using results of previously called API endpoints
         *
         * @param $sEndpoint
         * @return string
         * @throws Exception\InvalidJmesPath
         * @throws Exception\InvalidSegmentVariable
         * @throws Exception\NoTemplateValues
         */
        public function fillEndpointTemplateFromData(string $sEndpoint) {
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
        public function getTemplateValue(string $sTemplate) {
            if (strpos($sTemplate, '{') === 0) {
                $aValues = [];
                $sMatch  = trim($sTemplate, "{}");

                if (strpos($sMatch, 'jmes:') === 0) {
                    $sExpression = str_replace('jmes:', '', $sMatch);

                    try {
                        $aValues = JmesPath\Env::search($sExpression, $this->oData->all());
                    } catch (\RuntimeException $e) {
                        Log::e('MultiEndpointQuery.getTemplateValue.JMESPath.error', [
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
                            Log::e('MultiEndpointQuery.getTemplateValue.JMESPath', [
                                'template'   => $sTemplate,
                                'expression' => $sExpression,
                                'values'     => $aValues
                            ]);

                            throw new Exception\InvalidJmesPath('JmesPath Needs to return a flat array, this was a multidimensional array.  Consider the flatten projection operator []');
                        }
                    }

                    Log::d('MultiEndpointQuery.getTemplateValue.JMESPath', [
                        'template'   => $sTemplate,
                        'expression' => $sExpression,
                        'values'     => $aValues
                    ]);
                } else {
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

                            Log::d('MultiEndpointQuery.getTemplateValue.TableField', [
                                'template' => $sTemplate,
                                'values'   => $aValues
                            ]);
                        }
                    }
                }

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