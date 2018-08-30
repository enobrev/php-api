<?php
    namespace Enobrev\API\Middleware;

    use Adbar\Dot;
    use DateTime;
    use function Enobrev\dbg;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    class ResponseServerData implements MiddlewareInterface {
        const SYNC_DATE_FORMAT = 'Y-m-d H:i:s';

        /**
         * Process an incoming server request and return a response, optionally delegating
         * response creation to a handler.
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            /** @var Dot $oBuilder */
            $oBuilder = $oRequest->getAttribute(ResponseBuilder::class);
            if ($oBuilder) {
                $oNow = new DateTime;
                $oBuilder->set('_server', [
                    'timezone'      => $oNow->format('T'),
                    'timezone_gmt'  => $oNow->format('P'),
                    'date'          => $oNow->format(self::SYNC_DATE_FORMAT),
                    'date_w3c'      => $oNow->format(DateTime::W3C)
                ]);
                $oRequest = $oRequest->withAttribute(ResponseBuilder::class, $oBuilder);
            }

            return $oHandler->handle($oRequest);
        }
    }