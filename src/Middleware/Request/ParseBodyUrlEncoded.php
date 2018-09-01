<?php
    namespace Enobrev\API\Middleware\Request;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    class ParseBodyUrlEncoded implements MiddlewareInterface {
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $aContentType = $oRequest->getHeader('Content-Type');

            if (!$aContentType) {
                return $oHandler->handle($oRequest);
            }

            if (!preg_match('~^application/x-www-form-urlencoded($|[ ;])~', $aContentType[0])) {
                return $oHandler->handle($oRequest);
            }

            $sBody = (string) $oRequest->getBody();

            if (empty($sBody)) {
                return $oHandler->handle($oRequest);
            }

            $aParsedBody = $oRequest->getParsedBody();
            parse_str($sBody, $aParsedBody);

            return $oHandler->handle($oRequest->withParsedBody($aParsedBody));
        }
    }