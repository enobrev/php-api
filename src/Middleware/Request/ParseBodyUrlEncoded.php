<?php
    namespace Enobrev\API\Middleware\Request;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\Log;

    class ParseBodyUrlEncoded implements MiddlewareInterface {
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer = Log::startTimer('Enobrev.Middleware.ParseBodyUrlEncoded');
            $aContentType = $oRequest->getHeader('Content-Type');

            if (!$aContentType) {
                Log::dt($oTimer);
                Log::d('Enobrev.Middleware.ParseBodyUrlEncoded.NoContentType');
                return $oHandler->handle($oRequest);
            }

            if (!preg_match('~^application/x-www-form-urlencoded($|[ ;])~', $aContentType[0])) {
                Log::dt($oTimer);
                Log::d('Enobrev.Middleware.ParseBodyUrlEncoded.NotUrlEncoded');
                return $oHandler->handle($oRequest);
            }

            $sBody = (string) $oRequest->getBody();

            if (empty($sBody)) {
                Log::dt($oTimer);
                Log::d('Enobrev.Middleware.ParseBodyUrlEncoded.NoBody');
                return $oHandler->handle($oRequest);
            }

            $aParsedBody = $oRequest->getParsedBody();
            parse_str($sBody, $aParsedBody);

            Log::justAddContext([
                '#request' => [
                    'parameters' => [
                        'post'  => $aParsedBody && count($aParsedBody) ? json_encode($aParsedBody) : null
                    ]
                ]
            ]);

            Log::dt($oTimer);
            return $oHandler->handle($oRequest->withParsedBody($aParsedBody));
        }
    }