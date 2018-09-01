<?php
    declare(strict_types = 1);

    namespace Enobrev\API\Middleware;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use RuntimeException;

    use function Enobrev\dbg;

    /**
     * @package Enobrev\API\Middleware
     */
    class RequestHandler implements MiddlewareInterface {
        /**
         * Process a server request and return a response.
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $sClass = FastRoute::getRouteClassName($oRequest);

            if (!$sClass) {
                throw new RuntimeException('Empty request handler');
            }

            /** @var MiddlewareInterface|RequestHandlerInterface $oClass */
            $oClass = new $sClass;

            if ($oClass instanceof MiddlewareInterface) {
                return $oClass->process($oRequest, $oHandler);
            }

            if ($oClass instanceof RequestHandlerInterface) {
                return $oClass->handle($oRequest);
            }

            throw new RuntimeException(sprintf('Invalid request handler: %s', gettype($oClass)));
        }
    }
