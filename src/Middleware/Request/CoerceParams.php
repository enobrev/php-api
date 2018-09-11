<?php
    namespace Enobrev\API\Middleware\Request;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\Log;
    use Enobrev\API\Middleware\FastRoute;
    use Enobrev\API\Param;
    use Enobrev\API\Spec;
    use function Enobrev\dbg;

    class CoerceParams implements MiddlewareInterface {

        /**
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer = Log::startTimer('Enobrev.Middleware.CoerceParams');
            $oSpec = AttributeSpec::getSpec($oRequest);

            if ($oSpec instanceof Spec === false) {
                Log::dt($oTimer);
                return $oHandler->handle($oRequest);
            }

            $aCoerced = [
                'path'   => [],
                'query'  => [],
                'post'   => [],
                'header' => []
            ];

            $aPathParams = FastRoute::getPathParams($oRequest);
            if ($aPathParams) {
                foreach ($oSpec->getPathParams() as $sParam => $oParam) {
                    if (isset($aPathParams[$sParam])) {
                        if ($oParam instanceof Param\_Array) {
                            $aPathParams[$sParam] = explode(',', $aPathParams[$sParam]);
                            $aPathParams[$sParam] = array_map('trim', $aPathParams[$sParam]);
                            $aCoerced['path'][] = $sParam;
                        }
                    }
                }
                
                $oRequest = FastRoute::updatePathParams($oRequest, $aPathParams);
            }

            $aQueryParams = $oRequest->getQueryParams();
            if ($aQueryParams) {
                foreach ($oSpec->getQueryParams() as $sParam => $oParam) {
                    if (isset($aQueryParams[$sParam])) {
                        if ($oParam instanceof Param\_Array && is_string($aQueryParams[$sParam])) {
                            $aQueryParams[$sParam] = explode(',', $aQueryParams[$sParam]);
                            $aQueryParams[$sParam] = array_map('trim', $aQueryParams[$sParam]);
                            $aCoerced['query'][] = $sParam;
                        }
                    }
                }

                $oRequest = $oRequest->withQueryParams($aQueryParams);
            }

            $aPostParams = $oRequest->getParsedBody();
            if ($aPostParams) {
                foreach ($oSpec->getPostParams() as $sParam => $oParam) {
                    if (isset($aPostParams[$sParam])) {
                        if ($oParam instanceof Param\_Array && is_string($aPostParams[$sParam])) {
                            $aPostParams[$sParam] = explode(',', $aPostParams[$sParam]);
                            $aPostParams[$sParam] = array_map('trim', $aPostParams[$sParam]);
                            $aCoerced['post'][] = $sParam;
                        } else if ($oParam instanceof Param\_Object && is_array($aPostParams[$sParam])) {
                            $aPostParams[$sParam] = (object) $aPostParams[$sParam];
                            $aCoerced['post'][] = $sParam;
                        }
                    }
                }

                $oRequest = $oRequest->withParsedBody($aPostParams);
            }

            $aHeaderParams = $oRequest->getHeaders();
            if ($aHeaderParams) {
                foreach ($oSpec->getHeaderParams() as $sParam => $oParam) {
                    if (isset($aHeaderParams[$sParam])) {
                        $aHeader = [];
                        foreach($aHeaderParams[$sParam] as $sHeaderParam) {
                            if ($oParam instanceof Param\_Array && is_string($sHeaderParam)) {
                                $sHeaderParam = explode(',', $sHeaderParam);
                                $aHeader[]    = array_map('trim', $sHeaderParam);
                                $aCoerced['header'][] = $sParam;
                            } else if ($oParam instanceof Param\_Object && is_array($sHeaderParam)) {
                                $aHeader[]    = (object) $sHeaderParam;
                                $aCoerced['header'][] = $sParam;
                            } else {
                                $aHeader[]    = $sHeaderParam;
                            }
                        }
                        $oRequest = $oRequest->withHeader($sParam, $aHeader);
                    }
                }
            }

            Log::dt($oTimer, ['coerced' => $aCoerced]);
            return $oHandler->handle($oRequest);
        }
    }