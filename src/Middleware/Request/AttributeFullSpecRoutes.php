<?php
    namespace Enobrev\API\Middleware\Request;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\FullSpec;
    use Enobrev\API\RequestAttribute;
    use Enobrev\API\RequestAttributeInterface;
    use Enobrev\Log;

    class AttributeFullSpecRoutes implements MiddlewareInterface, RequestAttributeInterface {
        use RequestAttribute;

        /** @var FullSpec */
        private $oFullSpec;

        public function __construct(FullSpec $oFullSpec) {
            $this->oFullSpec = $oFullSpec;
        }

        public static function getRoutes(ServerRequestInterface $oRequest): ?array {
            return self::getAttribute($oRequest);
        }

        /**
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer = Log::startTimer('Enobrev.Middleware.FullSpecRoutes');
            $oRequest = self::setAttribute($oRequest, $this->oFullSpec->getRoutes());
            Log::dt($oTimer);

            return $oHandler->handle($oRequest);
        }

    }