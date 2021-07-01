<?php
    namespace Enobrev\API\Middleware\Request;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\Log;
    use function Enobrev\dbg;

    class LogStart implements MiddlewareInterface {
        private static bool $bLogObjects = false;
        private static array $aRedactPaths = [];

        public function __construct(bool $bLogObjects = false,  array $aRedactPaths = []) {
            self::$bLogObjects = $bLogObjects;
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

            $aLog = [
                '#request' => [
                    'method'     => $oRequest->getMethod(),
                    'host'       => $oURI->getHost(),
                    'path'       => $oURI->getPath(),
                    'uri'        => $aServerParams && isset($aServerParams['REQUEST_URI']) ? $aServerParams['REQUEST_URI'] : null,
                    'parameters' => [],
                    'headers'    => json_encode($oRequest->getHeaders()),
                    'referrer'   => $oRequest->hasHeader('referer') ? $oRequest->getHeaderLine('referer') : null
                ]
            ];

            $aQueryParams  = self::redactParamsFromLogs($oRequest, $aQueryParams);
            $aPostParams   = self::redactParamsFromLogs($oRequest, $aPostParams);

            if ($aQueryParams  && count($aQueryParams)) {
                $aLog['#request']['parameters']['query'] = self::$bLogObjects ? $aQueryParams : json_encode($aQueryParams);
            }

            if ($aPostParams  && count($aPostParams)) {
                $aLog['#request']['parameters']['post'] = self::$bLogObjects ? $aPostParams : json_encode($aPostParams);
            }

            Log::i('Enobrev.Middleware.LogStart', $aLog);

            return $oHandler->handle($oRequest);
        }
    }