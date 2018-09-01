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
                foreach ($oSpec->getPathParams() as $oParam) {
                    if ($oParam->is(Param::ARRAY) && isset($aPathParams[$oParam->sName])) {
                        $aPathParams[$oParam->sName] = explode(',', $aPathParams[$oParam->sName]);
                        $aPathParams[$oParam->sName] = array_map('trim', $aPathParams[$oParam->sName]);
                        $aCoerced[] = $oParam->sName;
                    }
                }
                
                $oRequest = FastRoute::updatePathParams($oRequest, $aPathParams);
            }

            $aQueryParams = $oRequest->getQueryParams();
            if ($aQueryParams) {
                foreach ($oSpec->getQueryParams() as $oParam) {
                    if ($oParam->is(Param::ARRAY) && isset($aQueryParams[$oParam->sName]) && is_string($aQueryParams[$oParam->sName])) {
                        $aQueryParams[$oParam->sName] = explode(',', $aQueryParams[$oParam->sName]);
                        $aQueryParams[$oParam->sName] = array_map('trim', $aQueryParams[$oParam->sName]);
                        $aCoerced[] = $oParam->sName;
                    }
                }

                $oRequest = $oRequest->withQueryParams($aQueryParams);
            }

            $aPostParams = $oRequest->getParsedBody();
            if ($aPostParams) {
                foreach ($oSpec->getPostParams() as $oParam) {
                    if ($oParam->is(Param::ARRAY) && isset($aPostParams[$oParam->sName]) && is_string($aPostParams[$oParam->sName])) {
                        $aPostParams[$oParam->sName] = explode(',', $aPostParams[$oParam->sName]);
                        $aPostParams[$oParam->sName] = array_map('trim', $aPostParams[$oParam->sName]);
                        $aCoerced[] = $oParam->sName;
                    }
                }

                $oRequest = $oRequest->withParsedBody($aPostParams);
            }

            Log::dt($oTimer, ['coerced' => $aCoerced]);
            return $oHandler->handle($oRequest);
        }
    }