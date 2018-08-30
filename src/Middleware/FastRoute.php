<?php
    declare(strict_types = 1);

    namespace Enobrev\API\Middleware;

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
    class FastRoute implements MiddlewareInterface {
        const ATTRIBUTE_REQUEST_HANDLER = 'PRIVATE-FASTROUTE-HANDLER';
        const ATTRIBUTE_PATH_PARAMS     = 'PRIVATE-FASTROUTE-PATH_PARAMS';

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

            $oRequest = $oRequest->withAttribute(self::ATTRIBUTE_REQUEST_HANDLER, $aRoute[1])
                                 ->withAttribute(self::ATTRIBUTE_PATH_PARAMS,     $aRoute[2]);

            return $oHandler->handle($oRequest);
        }
    }