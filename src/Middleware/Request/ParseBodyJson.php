<?php
    namespace Enobrev\API\Middleware\Request;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\Exception;
    use function Enobrev\dbg;

    class ParseBodyJson implements MiddlewareInterface {
        /**
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         * @throws Exception
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $aContentType = $oRequest->getHeader('Content-Type');

            if (!$aContentType) {
                return $oHandler->handle($oRequest);
            }

            $aParts = explode(';', $aContentType[0]);
            $sMime  = trim(array_shift($aParts));

            if (!preg_match('~[/+]json$~', $sMime)) {
                return $oHandler->handle($oRequest);
            }

            $sBody = (string) $oRequest->getBody();

            if (empty($sBody)) {
                return $oHandler->handle($oRequest);
            }

            $aParsedBody = json_decode($sBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error Parsing JSON Request Body: ' . json_last_error());
            }

            return $oHandler->handle($oRequest->withParsedBody($aParsedBody));
        }
    }