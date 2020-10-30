<?php
    namespace Enobrev\API\Middleware\Request;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\Log;
    use Enobrev\API\Middleware\FastRoute;
    use Enobrev\API\Spec;

    class CoerceParams implements MiddlewareInterface {

        /**
         * @param ServerRequestInterface  $oRequest
         * @param RequestHandlerInterface $oHandler
         *
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
                        $mCoerced = $oParam->coerce($aPathParams[$sParam]);
                        if ($mCoerced !== $aPathParams[$sParam]) {
                            $aPathParams[$sParam] = $mCoerced;
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
                        $mCoerced = $oParam->coerce($aQueryParams[$sParam]);
                        if ($mCoerced !== $aQueryParams[$sParam]) {
                            $aQueryParams[$sParam] = $mCoerced;
                            $aCoerced['query'][] = $sParam;
                        }
                    }
                }

                $oRequest = $oRequest->withQueryParams($aQueryParams);
            }

            $aPostParams = $oRequest->getParsedBody();
            if ($aPostParams) {
                foreach ($oSpec->resolvePostParams() as $sParam => $oParam) {
                    if (isset($aPostParams[$sParam])) {
                        $mCoerced = $oParam->coerce($aPostParams[$sParam]);
                        Log::d('Enobrev.Middleware.CoerceParams.post', ['original' => var_export($aPostParams[$sParam], true), 'coerced' => var_export($mCoerced, true)]);
                        if ($mCoerced !== $aPostParams[$sParam]) {
                            $aPostParams[$sParam] = $mCoerced;
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
                            $mCoerced = $oParam->coerce($sHeaderParam);
                            if ($mCoerced !== $sHeaderParam) {
                                $aCoerced['header'][] = $sParam;
                            }
                            $aHeader[] = $mCoerced;
                        }

                        $oRequest = $oRequest->withHeader($sParam, $aHeader);
                    }
                }
            }

            Log::dt($oTimer, ['coerced' => $aCoerced]);
            return $oHandler->handle($oRequest);
        }
    }