<?php
    namespace Enobrev\API\Middleware\Response;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\Middleware\ResponseBuilder;
    use Enobrev\Log;

    class MetadataRequest implements MiddlewareInterface {
        /**
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer   = Log::startTimer('Enobrev.Middleware.MetadataRequest');
            $oBuilder = ResponseBuilder::get($oRequest);
            if ($oBuilder) {
                $oBuilder->mergeRecursiveDistinct('_request', [
                    'method'     => $oRequest->getMethod(),
                    'path'       => $oRequest->getUri()->getPath(),
                    'params'     => [
                        'query' => $oRequest->getQueryParams(),
                        'post'  => $oRequest->getParsedBody()
                    ],
                    'headers'    => json_encode($oRequest->getHeaders())
                ]);
                $oRequest = ResponseBuilder::update($oRequest, $oBuilder);
            }

            Log::dt($oTimer);
            return $oHandler->handle($oRequest);
        }
    }