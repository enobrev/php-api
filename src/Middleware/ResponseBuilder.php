<?php
    declare(strict_types = 1);

    namespace Enobrev\API\Middleware;

    use Adbar\Dot;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    /**
     * @package Enobrev\API\Middleware
     */
    class ResponseBuilder implements MiddlewareInterface {
        /**
         * Process a server request and return a response.
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            return $oHandler->handle($oRequest->withAttribute(self::class, new Dot()));
        }
    }