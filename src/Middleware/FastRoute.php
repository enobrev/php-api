<?php
    declare(strict_types = 1);

    namespace Enobrev\API\Middleware;

    use Enobrev\API\RequestAttribute;
    use Enobrev\API\RequestAttributeInterface;
    use function Enobrev\dbg;
    use FastRoute\Dispatcher;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use Zend\Diactoros\Response;

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
         * @var Dispatcher FastRoute dispatcher
         */
        private $oRouter;

        /**
         * Set the Dispatcher instance.
         * @param Dispatcher $oRouter
         */
        public function __construct(Dispatcher $oRouter) {
            $this->oRouter = $oRouter;
        }

        /**
         * Process a server request and return a response.
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $aRoute = $this->oRouter->dispatch($oRequest->getMethod(), $oRequest->getUri()->getPath());

            if ($aRoute[0] === Dispatcher::NOT_FOUND) {
                return new Response('php://memory', 404);
            }

            if ($aRoute[0] === Dispatcher::METHOD_NOT_ALLOWED) {
                return (new Response('php://memory', 405))->withHeader('Allow', implode(', ', $aRoute[1]));
            }
            
            $oRequest = self::setAttribute($oRequest, (object) [
                'class'      => $aRoute[1],
                'pathParams' => $aRoute[2]
            ]);

            return $oHandler->handle($oRequest);
        }
    }