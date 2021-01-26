<?php
    declare(strict_types = 1);

    namespace Enobrev\API\Middleware;

    use Enobrev\API\Middleware\Request\AttributeSpec;
    use Exception;

    use Laminas\Diactoros\Response\JsonResponse;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\HTTP;
    use Enobrev\Log;
    use function Enobrev\dbg;

    /**
     * @package Enobrev\API\Middleware
     */
    class ResponseBuilderDone implements MiddlewareInterface {
        private static $aRedactSpecPaths = [];

        public function __construct(array $aRedactSpecPaths = []) {
            self::$aRedactSpecPaths = $aRedactSpecPaths;
        }

        private function shouldRedact(ServerRequestInterface $oRequest): bool {
            $oSpec    = AttributeSpec::getSpec($oRequest);
            $aMethods = self::$aRedactSpecPaths[$oSpec->getPathForDocs()] ?? null;
            if (!$aMethods) {
                return false;
            }

            return in_array($oSpec->getHttpMethod(), $aMethods);
        }

        /**
         * Process a server request and return a response.
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oResponse = new JsonResponse(ResponseBuilder::get($oRequest)->all(), HTTP\OK);

            if ($this->shouldRedact($oRequest)) {
                Log::i('Enobrev.Middleware.ResponseBuilderDone', [
                    '#response' => [
                        'status'  => $oResponse->getStatusCode(),
                        'headers' => json_encode($oResponse->getHeaders())
                    ],
                    'body'     => '__REDACTED__'
                ]);

                return $oResponse;
            }

            $sBody = null;
            try {
                $sBody = json_encode($oResponse->getPayload(), JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
            } catch (Exception $e) {
                Log::ex('Enobrev.Middleware.ResponseBuilderDone', $e);
            }

            if (!$sBody) {
                $sBody = '{"log_error": "not_encoded"}';
            }

            Log::i('Enobrev.Middleware.ResponseBuilderDone', [
                '#response' => [
                    'status'  => $oResponse->getStatusCode(),
                    'headers' => json_encode($oResponse->getHeaders()),
                    'size'    => strlen($sBody)
                ],
                'body'     => $sBody
            ]);

            return $oResponse;
        }
    }