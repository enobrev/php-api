<?php
    declare(strict_types = 1);

    namespace Enobrev\API\Middleware;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use RuntimeException;

    /**
     * @package Enobrev\API\Middleware
     */
    class RequestHandler implements MiddlewareInterface {
        /**
         * Process a server request and return a response.
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $sClass = $oRequest->getAttribute(FastRoute::ATTRIBUTE_REQUEST_HANDLER);

            if (empty($sClass)) {
                throw new RuntimeException('Empty request handler');
            }

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
