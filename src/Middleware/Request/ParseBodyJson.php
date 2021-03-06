<?php
    namespace Enobrev\API\Middleware\Request;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\Exception;
    use Enobrev\Log;

    class ParseBodyJson implements MiddlewareInterface {
        private static bool $bLogObjects = false;

        public function __construct(bool $bLogObjects = false) {
            self::$bLogObjects = $bLogObjects;
        }

        /**
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         * @throws Exception
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer = Log::startTimer('Enobrev.Middleware.ParseBodyJson');
            $aContentType = $oRequest->getHeader('Content-Type');

            if (!$aContentType) {
                Log::dt($oTimer);
                Log::d('Enobrev.Middleware.ParseBodyJson.NoContentType');
                return $oHandler->handle($oRequest);
            }

            $aParts = explode(';', $aContentType[0]);
            $sMime  = trim(array_shift($aParts));

            if (!preg_match('~[/+]json$~', $sMime)) {
                Log::dt($oTimer);
                Log::d('Enobrev.Middleware.ParseBodyJson.NotJson');
                return $oHandler->handle($oRequest);
            }

            $sBody = (string) $oRequest->getBody();

            if (empty($sBody)) {
                Log::dt($oTimer);
                Log::d('Enobrev.Middleware.ParseBodyJson.NoBody');
                return $oHandler->handle($oRequest);
            }

            $aParsedBody = json_decode($sBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::dt($oTimer);
                Log::e('Enobrev.Middleware.ParseBodyJson.ParseError');
                throw new Exception('Error Parsing JSON Request Body: ' . json_last_error());
            }

            $aRedacted  = LogStart::redactParamsFromLogs($oRequest, $aParsedBody);

            if ($aRedacted && count($aRedacted)) {
                Log::justAddContext([
                    '#request' => [
                        'parameters' => [
                            'post' => self::$bLogObjects ? $aRedacted : json_encode($aRedacted)
                        ]
                    ]
                ]);
            }

            Log::dt($oTimer);
            return $oHandler->handle($oRequest->withParsedBody($aParsedBody));
        }
    }