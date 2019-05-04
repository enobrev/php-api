<?php
    declare(strict_types = 1);

    namespace Enobrev\API\Middleware;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use RuntimeException;

    use Enobrev\Log;

    /**
     * @package Enobrev\API\Middleware
     */
    class RequestHandler implements MiddlewareInterface {
        /**
         * Process a server request and return a response.
         *
         * @param ServerRequestInterface  $oRequest
         * @param RequestHandlerInterface $oHandler
         *
         * @return ResponseInterface
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer = Log::startTimer('Enobrev.Middleware.RequestHandler');
            $sClass = FastRoute::getRouteClassName($oRequest);

            if (!$sClass) {
                throw new RuntimeException('Empty request handler');
            }

            /** @var MiddlewareInterface|RequestHandlerInterface $oClass */
            $oClass = new $sClass;

            if ($oClass instanceof MiddlewareInterface) {
                Log::dt($oTimer, ['class' => $sClass, 'type' => 'MiddlewareInterface']);
                return $oClass->process($oRequest, $oHandler);
            }

            if ($oClass instanceof RequestHandlerInterface) {
                Log::dt($oTimer, ['class' => $sClass, 'type' => 'RequestHandlerInterface']);
                return $oClass->handle($oRequest);
            }

            Log::dt($oTimer, ['class' => $sClass, 'type' => gettype($oClass)]);
            Log::e('Enobrev.Middleware.RequestHandler.Error');
            throw new RuntimeException(sprintf('Invalid request handler: %s', gettype($oClass)));
        }
    }
