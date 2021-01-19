<?php
    namespace Enobrev\API\Middleware\Request;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\Log;
    use function Enobrev\dbg;

    class LogStart implements MiddlewareInterface {
        private static $aRedactPaths = [];

        public function __construct(array $aRedactPaths = []) {
            self::$aRedactPaths = $aRedactPaths;
        }

        public static function redactParamsFromLogs(ServerRequestInterface $oRequest, ?array $aParams): ?array {
            if (!$aParams) {
                return $aParams;
            }

            $sPath       = $oRequest->getUri()->getPath();
            $sMethod     = $oRequest->getMethod();

            $aMatch = null;
            foreach(array_keys(self::$aRedactPaths) as $sMatch) {
                if (preg_match($sMatch, $sPath)) {
                    $aMatch = self::$aRedactPaths[$sMatch];
                    break;
                }
            }

            if (!$aMatch) {
                return $aParams;
            }

            $aRedactKeys = $aMatch[$sMethod] ?? null;
            if (!$aRedactKeys) {
                return $aParams;
            }

            foreach($aRedactKeys as $sKey) {
                if (isset($aParams[$sKey])) {
                    $aParams[$sKey] = '__REDACTED__';
                }
            }

            return $aParams;
        }

        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $aQueryParams  = $oRequest->getQueryParams();
            $aPostParams   = $oRequest->getParsedBody();
            $aServerParams = $oRequest->getServerParams();
            $oURI          = $oRequest->getUri();

            $aQueryParams  = self::redactParamsFromLogs($oRequest, $aQueryParams);
            $aPostParams   = self::redactParamsFromLogs($oRequest, $aPostParams);

            Log::i('Enobrev.Middleware.LogStart', [
                '#request' => [
                    'method'     => $oRequest->getMethod(),
                    'host'       => $oURI->getHost(),
                    'path'       => $oURI->getPath(),
                    'uri'        => $aServerParams && isset($aServerParams['REQUEST_URI']) ? $aServerParams['REQUEST_URI'] : null,
                    'parameters' => [
                        'query'  => $aQueryParams && count($aQueryParams) ? json_encode($aQueryParams) : null,
                        'post'   => $aPostParams  && count($aPostParams)  ? json_encode($aPostParams)  : null
                    ],
                    'headers'    => json_encode($oRequest->getHeaders()),
                    'referrer'   => $oRequest->hasHeader('referer') ? $oRequest->getHeaderLine('referer') : null
                ]
            ]);

            return $oHandler->handle($oRequest);
        }
    }