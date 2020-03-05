<?php
    declare(strict_types = 1);

    namespace Enobrev\API\Middleware;

    use FastRoute as FastRouteLib;
    use Laminas\Diactoros\Response;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\HTTP;
    use Enobrev\API\Exception\EndpointNotFound;
    use Enobrev\API\Exception\MethodNotAllowed;
    use Enobrev\API\Method;
    use Enobrev\API\Middleware\Request\AttributeFullSpecRoutes;
    use Enobrev\API\RequestAttribute;
    use Enobrev\API\RequestAttributeInterface;
    use Enobrev\Log;

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
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         * @throws EndpointNotFound
         * @throws MethodNotAllowed
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer            = Log::startTimer('Enobrev.Middleware.FastRoute');
            $oTimerDispatcher  = Log::startTimer('Enobrev.Middleware.FastRoute.Dispatcher');
            $oRouter = FastRouteLib\simpleDispatcher(static function(FastRouteLib\RouteCollector $oRouteCollector) use ($oRequest) {
                $aRoutes = AttributeFullSpecRoutes::getRoutes($oRequest);
                if ($aRoutes) {
                    foreach ($aRoutes as $sPath => $aMethods) {
                        foreach ($aMethods as $sMethod => $sClass) {
                            $oRouteCollector->addRoute($sMethod, $sPath, $sClass);
                        }
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
                throw new EndpointNotFound;
            }

            if ($aRoute[0] === FastRouteLib\Dispatcher::METHOD_NOT_ALLOWED) {
                $aMethods  = array_unique(array_merge( $aRoute[1], [Method\OPTIONS]));
                $oResponse = (new Response())->withHeader('Allow', implode(', ', $aMethods));

                if ($oRequest->getMethod() === Method\OPTIONS) {
                    Log::i('Enobrev.Middleware.FastRoute', [
                        '#response' => [
                            'status' => HTTP\NO_CONTENT,
                        ]
                    ]);

                    $oResponse = $oResponse->withStatus(HTTP\NO_CONTENT);
                    Log::dt($oTimer);
                    return $oResponse;
                }

                Log::d('Enobrev.Middleware.FastRoute.MethodNotAllowed');
                throw new MethodNotAllowed('This HTTP Method is not Allowed for this endpoint');
            }

            [$_, $sClass, $aPathParams] = $aRoute;

            $oRequest = self::setAttribute($oRequest, (object) [
                'class'      => $sClass,
                'pathParams' => $aPathParams
            ]);

            $oBuilder = ResponseBuilder::get($oRequest);
            if ($oBuilder) {
                if ($oBuilder->has('_request')) {
                    $oBuilder->mergeRecursiveDistinct('_request.params.path', $aPathParams);
                }

                $oRequest = ResponseBuilder::update($oRequest, $oBuilder);
            }

            Log::dt($oTimer, [
                'method'     => $oRequest->getMethod(),
                'path'       => $oRequest->getUri()->getPath(),
                'class'      => $sClass,
                'pathParams' => json_encode($aPathParams)
            ]);

            Log::justAddContext([
                '#request' => [
                    'parameters' => [
                        'path'  => $aPathParams && count($aPathParams) ? json_encode($aPathParams) : null
                    ]
                ]
            ]);

            return $oHandler->handle($oRequest);
        }
    }