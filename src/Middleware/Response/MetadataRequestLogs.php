<?php
    namespace Enobrev\API\Middleware\Response;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\Middleware\ResponseBuilder;
    use Enobrev\Log;

    class MetadataRequestLogs implements MiddlewareInterface {
        /**
         * Process an incoming server request and return a response, optionally delegating
         * response creation to a handler.
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oBuilder = ResponseBuilder::get($oRequest);
            if ($oBuilder) {
                $oBuilder->set('_request.logs', [
                    'thread'  => Log::getThreadHashForOutput(),
                    'request' => Log::getRequestHashForOutput()
                ]);
                ResponseBuilder::update($oRequest, $oBuilder);
            }

            return $oHandler->handle($oRequest);
        }
    }