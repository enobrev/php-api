<?php
    namespace Enobrev\API\Middleware\Request;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\Log;

    class LogStart implements MiddlewareInterface {
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $aQueryParams  = $oRequest->getQueryParams();
            $aPostParams   = $oRequest->getParsedBody();
            $aServerParams = $oRequest->getServerParams();
            $oURI          = $oRequest->getUri();

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