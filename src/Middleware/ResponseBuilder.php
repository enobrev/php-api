<?php
    declare(strict_types = 1);

    namespace Enobrev\API\Middleware;

    use Adbar\Dot;
    use Enobrev\API\RequestAttribute;
    use Enobrev\API\RequestAttributeInterface;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    /**
     * @package Enobrev\API\Middleware
     */
    class ResponseBuilder implements MiddlewareInterface, RequestAttributeInterface {
        use RequestAttribute;

        public static function get(ServerRequestInterface $oRequest): Dot {
            return self::getAttribute($oRequest);
        }

        public static function update(ServerRequestInterface $oRequest, Dot $oResponse):ServerRequestInterface {
            return self::setAttribute($oRequest, $oResponse);
        }

        /**
         * Process a server request and return a response.
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            return $oHandler->handle(self::update($oRequest, new Dot()));
        }
    }