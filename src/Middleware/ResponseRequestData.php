<?php
    namespace Enobrev\API\Middleware;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use Zend\Diactoros\Response\JsonResponse;

    class ResponseRequestData implements MiddlewareInterface {
        /**
         * Process an incoming server request and return a response, optionally delegating
         * response creation to a handler.
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oResponse = $oHandler->handle($oRequest);

            switch (true) {
                case $oResponse instanceof JsonResponse:
                    $oPayload = $oResponse->getPayload();

                    $oPayload->_request = (object) [
                        'method'     => $oRequest->getMethod(),
                        'path'       => $oRequest->getUri()->getPath(),
                        'attributes' => $oRequest->getAttributes(),
                        'query'      => $oRequest->getQueryParams(),
                        'headers'    => $oRequest->getHeaders()
                    ];

                    return $oResponse->withPayload($oPayload);
                    break;

                default:
                    return $oResponse;
                    break;
            }
        }
    }