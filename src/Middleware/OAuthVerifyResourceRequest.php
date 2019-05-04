<?php
    namespace Enobrev\API\Middleware;

    use Middlewares;
    use OAuth2\Request;
    use OAuth2\Response  as OAuth_Response;
    use OAuth2\Server    as OAuth_Server;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\HTTP;
    use Enobrev\API\Middleware\Request\AttributeSpec;
    use Enobrev\API\RequestAttribute;
    use Enobrev\API\RequestAttributeInterface;
    use Enobrev\API\Spec;
    use Enobrev\Log;

    class OAuthVerifyResourceRequest implements MiddlewareInterface, RequestAttributeInterface {
        use RequestAttribute;

        /** @var OAuth_Server */
        private $oAuthServer;

        public function __construct(OAuth_Server $oAuthServer) {
            $this->oAuthServer = $oAuthServer;
        }

        public static function getAccessToken(ServerRequestInterface $oRequest): ?array {
            return self::getAttribute($oRequest);
        }

        /**
         * Process a server request and return a response.
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer = Log::startTimer('Enobrev.Middleware.OAuthVerifyResourceRequest');
            $oSpec  = AttributeSpec::getSpec($oRequest);
            if ($oSpec instanceof Spec === false) {
                Log::dt($oTimer);
                return $oHandler->handle($oRequest);
            }

            $aHeaders = $oRequest->getHeaders();
            $aFlatHeaders = [];
            foreach($aHeaders as $sHeader => $aHeader) {
                $aFlatHeaders[$sHeader] = array_shift($aHeader);
            }

            $oAuthRequest = new Request(
                $oRequest->getQueryParams()    ?? [],
                $oRequest->getParsedBody()    ?? [],
                $oRequest->getAttributes()  ?? [],
                $oRequest->getCookieParams()  ?? [],
                $oRequest->getUploadedFiles()   ?? [],
                $oRequest->getServerParams()   ?? [],
                $oRequest->getBody(),
                $aFlatHeaders
            );

            $bAuth = $this->oAuthServer->verifyResourceRequest($oAuthRequest, null, $oSpec->getScopeList());
            if ($bAuth) {
                $aData = $this->oAuthServer->getAccessTokenData($oAuthRequest);
                $oRequest = self::setAttribute($oRequest, $aData);

                Log::dt($oTimer);
                return $oHandler->handle($oRequest);
            }

            if ($oSpec->isPublic()) {
                Log::dt($oTimer);
                return $oHandler->handle($oRequest);
            }

            /** @var OAuth_Response $oResponse */
            $oResponse   = $this->oAuthServer->getResponse();
            $iStatusCode = $oResponse->getStatusCode();

            if ($iStatusCode >= HTTP\BAD_REQUEST) {
                Log::dt($oTimer);
                $sError       = $oResponse->getParameter('error');
                $sDescription = $oResponse->getParameter('error_description');

                $aContext     = [$oResponse->getStatusText()];

                if ($sError) {
                    $aContext = [$sError];
                }

                if ($sDescription) {
                    $aContext = [$sDescription];
                }

                throw Middlewares\HttpErrorException::create($iStatusCode, $aContext);
            }

            Log::dt($oTimer);
            return $oHandler->handle($oRequest);
        }
    }
