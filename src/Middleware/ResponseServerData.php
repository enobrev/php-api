<?php
    namespace Enobrev\API\Middleware;

    use DateTime;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use Zend\Diactoros\Response\JsonResponse;

    class ResponseServerData implements MiddlewareInterface {
        const SYNC_DATE_FORMAT = 'Y-m-d H:i:s';

        /**
         * Process an incoming server request and return a response, optionally delegating
         * response creation to a handler.
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oResponse = $oHandler->handle($oRequest);

            switch (true) {
                case $oResponse instanceof JsonResponse:
                    $oPayload = $oResponse->getPayload();

                    $oNow = new \DateTime;
                    $oPayload->_server = (object) [
                        'timezone'      => $oNow->format('T'),
                        'timezone_gmt'  => $oNow->format('P'),
                        'date'          => $oNow->format(self::SYNC_DATE_FORMAT),
                        'date_w3c'      => $oNow->format(DateTime::W3C)
                    ];

                    return $oResponse->withPayload($oPayload);
                    break;

                default:
                    return $oResponse;
                    break;
            }
        }
    }