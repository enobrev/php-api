<?php
    declare(strict_types = 1);

    namespace Enobrev\API\Middleware;

    use FastRoute as FastRouteLib;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use Zend\Diactoros\Response;

    use Enobrev\API\HTTP;
    use Enobrev\API\Method;
    use Enobrev\API\Middleware\Request\AttributeFullSpecRoutes;
    use Enobrev\API\RequestAttribute;
    use Enobrev\API\RequestAttributeInterface;
    use Enobrev\Log;

    use function Enobrev\dbg;

    /**
     * Heavily Inspired By https://github.com/middlewares/fast-route/
     * @package Enobrev\API\Middleware
     */
    class FastRoute implements MiddlewareInterface, RequestAttributeInterface {
        use RequestAttribute;

        public static function getRouteClassName(ServerRequestInterface $oRequest): ?string {
            $oRoute = self::getAttribute($oRequest);
            return $oRoute ? $oRoute->class : null;
        }

        public static function getPathParams(ServerRequestInterface $oRequest): ?array {
            $oRoute = self::getAttribute($oRequest);
            return $oRoute ? $oRoute->pathParams : null;
        }

        public static function updatePathParams(ServerRequestInterface $oRequest, $aParams):ServerRequestInterface {
            $oRoute = self::getAttribute($oRequest);
            $oRoute->pathParams = $aParams;
            return self::setAttribute($oRequest, $oRoute);
        }

        /**
         * Process a server request and return a response.
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer            = Log::startTimer('Enobrev.Middleware.FastRoute');
            $oTimerDispatcher  = Log::startTimer('Enobrev.Middleware.FastRoute.Dispatcher');
            $oRouter = FastRouteLib\simpleDispatcher(function(FastRouteLib\RouteCollector $oRouteCollector) use ($oRequest) {
                $aRoutes = AttributeFullSpecRoutes::getRoutes($oRequest);
                foreach($aRoutes as $sPath => $aMethods) {
                    foreach($aMethods as $sMethod => $sClass) {
                        $oRouteCollector->addRoute($sMethod, $sPath, $sClass);
                    }
                }
            });
            Log::dt($oTimerDispatcher);
            Log::d('Enobrev.Middleware.FastRoute', [
                'method'     => $oRequest->getMethod(),
                'path'       => $oRequest->getUri()->getPath()
            ]);

            $aRoute = $oRouter->dispatch($oRequest->getMethod(), $oRequest->getUri()->getPath());

            if ($aRoute[0] === FastRouteLib\Dispatcher::NOT_FOUND) {
                Log::w('Enobrev.Middleware.FastRoute.NotFound');
                Log::dt($oTimer);
                return (new Response())->withStatus(HTTP\NOT_FOUND);
            }

            if ($aRoute[0] === FastRouteLib\Dispatcher::METHOD_NOT_ALLOWED) {
                $aMethods  = array_unique(array_merge( $aRoute[1], [Method\OPTIONS]));
                $oResponse = (new Response())->withHeader('Allow', implode(', ', $aMethods));

                if ($oRequest->getMethod() === Method\OPTIONS) {
                    $oResponse = $oResponse->withStatus(HTTP\NO_CONTENT);
                } else {
                    Log::d('Enobrev.Middleware.FastRoute.MethodNotAllowed');
                    $oResponse = $oResponse->withStatus(HTTP\METHOD_NOT_ALLOWED);
                }

                Log::dt($oTimer);
                return $oResponse;
            }

            $oRequest = self::setAttribute($oRequest, (object) [
                'class'      => $aRoute[1],
                'pathParams' => $aRoute[2]
            ]);

            Log::dt($oTimer, [
                'method'     => $oRequest->getMethod(),
                'path'       => $oRequest->getUri()->getPath(),
                'class'      => $aRoute[1],
                'pathParams' => $aRoute[2]
            ]);
            return $oHandler->handle($oRequest);
        }
    }