<?php
    namespace Enobrev\API\Middleware;

    use Adbar\Dot;
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
            $sClass = $oRequest->getAttribute(FastRoute::ATTRIBUTE_REQUEST_HANDLER);

            if (empty($sClass)) {
                return $oHandler->handle($oRequest);
            }

            /** @var SpecInterface $oClass */
            $oClass = new $sClass;

            if ($oClass instanceof SpecInterface === false) {
                return $oHandler->handle($oRequest);
            }

            $oSpec = $oClass->spec();

            $aPathParams = $oRequest->getAttribute(FastRoute::ATTRIBUTE_PATH_PARAMS);
            if ($aPathParams) {
                foreach ($oSpec->PathParams as $oParam) {
                    if ($oParam->is(Param::ARRAY) && isset($aPathParams[$oParam->sName])) {
                        $aPathParams[$oParam->sName] = explode(',', $aPathParams[$oParam->sName]);
                    }
                }
                
                $oRequest = $oRequest->withAttribute(FastRoute::ATTRIBUTE_PATH_PARAMS, $aPathParams);
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