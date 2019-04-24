<?php
    namespace Enobrev\API\Middleware\Request;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\Middleware\FastRoute;
    use Enobrev\API\RequestAttribute;
    use Enobrev\API\RequestAttributeInterface;
    use Enobrev\API\Spec;
    use Enobrev\API\SpecInterface;
    use Enobrev\Log;

    use function Enobrev\dbg;

    class AttributeSpec implements MiddlewareInterface, RequestAttributeInterface {
        use RequestAttribute;

        public static function getSpec(ServerRequestInterface $oRequest): ?Spec {
            return self::getAttribute($oRequest);
        }

        /**
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer = Log::startTimer('Enobrev.Middleware.AttributeSpec');
            $sClass = FastRoute::getRouteClassName($oRequest);

            if (!$sClass) {
                return $oHandler->handle($oRequest);
            }

            /** @var SpecInterface $oClass */
            $oClass = new $sClass;

            if ($oClass instanceof SpecInterface === false) {
                return $oHandler->handle($oRequest);
            }

            $oSpec = $oClass->spec();
            $oRequest = self::setAttribute($oRequest, $oSpec);

            if (!Log::hasPurpose()) {
                Log::setPurpose($oSpec->getSummary());
            }

            Log::justAddContext([
                '#spec' => [
                    'method'        => $oSpec->getHttpMethod(),
                    'path'          => $oSpec->getPath(),
                    'scopes'        => explode(',', $oSpec->getScopeList(',')),
                    'public'        => $oSpec->isPublic(),
                    'deprecated'    => $oSpec->isDeprecated()
                ]
            ]);

            Log::dt($oTimer);
            return $oHandler->handle($oRequest);
        }

    }