<?php
    namespace Enobrev\API\Middleware;

    use Adbar\Dot;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use Zend\Diactoros\Response\JsonResponse;

    use Enobrev\Log;

    class ResponseRequestLogData implements MiddlewareInterface {
        /**
         * Process an incoming server request and return a response, optionally delegating
         * response creation to a handler.
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            /** @var Dot $oBuilder */
            $oBuilder = $oRequest->getAttribute(ResponseBuilder::class);
            if ($oBuilder) {
                $oBuilder->set('_request.logs', [
                    'thread'  => Log::getThreadHashForOutput(),
                    'request' => Log::getRequestHashForOutput()
                ]);
                $oRequest = $oRequest->withAttribute(ResponseBuilder::class, $oBuilder);
            }

            return $oHandler->handle($oRequest);
        }
    }