<?php
    namespace Enobrev\API\Middleware;

    use Adbar\Dot;
    use Enobrev\API\FullSpec;
    use Enobrev\API\Param;
    use Enobrev\API\Spec;
    use Enobrev\API\SpecInterface;
    use function Enobrev\dbg;
    use JsonSchema\Constraints\Constraint;
    use JsonSchema\Validator;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use Zend\Diactoros\Response\JsonResponse;

    class CoerceArrayParams implements MiddlewareInterface {

        /**
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oSpec = RequestAttributeSpec::getSpec($oRequest);

            if ($oSpec instanceof Spec === false) {
                return $oHandler->handle($oRequest);
            }

            $aPathParams = FastRoute::getPathParams($oRequest);
            if ($aPathParams) {
                foreach ($oSpec->PathParams as $oParam) {
                    if ($oParam->is(Param::ARRAY) && isset($aPathParams[$oParam->sName])) {
                        $aPathParams[$oParam->sName] = explode(',', $aPathParams[$oParam->sName]);
                    }
                }
                
                $oRequest = FastRoute::updatePathParams($oRequest, $aPathParams);
            }

            $aQueryParams = $oRequest->getQueryParams();
            if ($aQueryParams) {
                foreach ($oSpec->QueryParams as $oParam) {
                    if ($oParam->is(Param::ARRAY) && isset($aQueryParams[$oParam->sName])) {
                        $aQueryParams[$oParam->sName] = explode(',', $aQueryParams[$oParam->sName]);
                    }

                    $oRequest = $oRequest->withQueryParams($aQueryParams);
                }
            }

            return $oHandler->handle($oRequest);
        }
    }