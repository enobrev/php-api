<?php
    namespace Enobrev\API\Middleware;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use OAuth2\Response  as OAuth_Response;
    use OAuth2\Request   as OAuth_Request;
    use OAuth2\Server    as OAuth_Server;
    use Zend\Diactoros\Response;

    use Enobrev\API\HTTP;
    use Enobrev\API\Middleware\Request\AttributeSpec;
    use Enobrev\API\RequestAttribute;
    use Enobrev\API\RequestAttributeInterface;
    use Enobrev\API\Spec;

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
            $oSpec = AttributeSpec::getSpec($oRequest);
            if ($oSpec instanceof Spec === false) {
                return $oHandler->handle($oRequest);
            }

            $oBuilder = ResponseBuilder::get($oRequest);

            if ($oSpec->isPublic()) {
                return $oHandler->handle($oRequest);
            }

            $oAuthRequest = OAuth_Request::createFromGlobals();
            $bAuth        = $this->oAuthServer->verifyResourceRequest($oAuthRequest, null, $oSpec->getScopeList());
            if ($bAuth) {
                $aData = $this->oAuthServer->getAccessTokenData($oAuthRequest);
                $oRequest = self::setAttribute($oRequest, $aData);
                /*
                if ($oData) {
                    $oRequest = self::setAttribute($oRequest, Table\AuthToken::getById($oData['access_token']));
                }
                */
            } else {
                /** @var OAuth_Response $oResponse */
                $oResponse   = $this->oAuthServer->getResponse();
                $iStatusCode = $oResponse->getStatusCode();

                if ($iStatusCode >= HTTP\BAD_REQUEST) {
                    $oBuilder->set('_request.auth.error.code',     $oResponse->getStatusCode() . ' Error');
                    $oBuilder->set('_request.auth.error.message',  $oResponse->getStatusText());

                    if ($oResponse->getParameter('error')) {
                        $sErrorCode     = $oResponse->getParameter('error');
                        $sErrorMessage  = $oResponse->getParameter('error_description');

                        if ($iStatusCode == HTTP\UNAUTHORIZED && $sErrorCode == 'invalid_token' && strpos($sErrorMessage, 'expired') !== false) {
                            $sErrorMessage = 'Expired Token';
                        }

                        $oBuilder->set('_request.auth.error.code',     $sErrorCode);
                        $oBuilder->set('_request.auth.error.message',  $sErrorMessage);
                    }

                    ResponseBuilder::update($oRequest, $oBuilder);

                    return new Response\JsonResponse(ResponseBuilder::get($oRequest)->all(), $iStatusCode);
                }
            }

            return $oHandler->handle($oRequest);
        }
    }
