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

            $aCoerced = [];

            $aPathParams = FastRoute::getPathParams($oRequest);
            if ($aPathParams) {
                foreach ($oSpec->getPathParams() as $sParam => $oParam) {
                    if ($oParam instanceof Param\_Array && isset($aPathParams[$sParam])) {
                        $aPathParams[$sParam] = explode(',', $aPathParams[$sParam]);
                        $aPathParams[$sParam] = array_map('trim', $aPathParams[$sParam]);
                        $aCoerced[] = $sParam;
                    }
                }
                
                $oRequest = FastRoute::updatePathParams($oRequest, $aPathParams);
            }

            $aQueryParams = $oRequest->getQueryParams();
            if ($aQueryParams) {
                foreach ($oSpec->getQueryParams() as $sParam => $oParam) {
                    if ($oParam instanceof Param\_Array && isset($aQueryParams[$sParam]) && is_string($aQueryParams[$sParam])) {
                        $aQueryParams[$sParam] = explode(',', $aQueryParams[$sParam]);
                        $aQueryParams[$sParam] = array_map('trim', $aQueryParams[$sParam]);
                        $aCoerced[] = $sParam;
                    }
                }

                $oRequest = $oRequest->withQueryParams($aQueryParams);
            }

            $aPostParams = $oRequest->getParsedBody();
            if ($aPostParams) {
                foreach ($oSpec->getPostParams() as $sParam => $oParam) {
                    if ($oParam instanceof Param\_Array && isset($aPostParams[$sParam]) && is_string($aPostParams[$sParam])) {
                        $aPostParams[$sParam] = explode(',', $aPostParams[$sParam]);
                        $aPostParams[$sParam] = array_map('trim', $aPostParams[$sParam]);
                        $aCoerced[] = $sParam;
                    }
                }

                $oRequest = $oRequest->withParsedBody($aPostParams);
            }

            Log::dt($oTimer, ['coerced' => $aCoerced]);
            return $oHandler->handle($oRequest);
        }
    }