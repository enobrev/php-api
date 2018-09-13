<?php
    namespace Enobrev\API\Middleware\Response;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\Middleware\ResponseBuilder;
    use Enobrev\Log;

    class MetadataResponse implements MiddlewareInterface {
        /**
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer = Log::startTimer('Enobrev.Middleware.MetadataResponse');
            $oBuilder = ResponseBuilder::get($oRequest);
            if ($oBuilder) {
                $oBuilder->set('_request.status', $oHandler->handle($oRequest)->getStatusCode());
                $oRequest = ResponseBuilder::update($oRequest, $oBuilder);
            }

            Log::dt($oTimer);
            return $oHandler->handle($oRequest);
        }
    }