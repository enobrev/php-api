<?php
    declare(strict_types = 1);

    namespace Enobrev\API\Middleware;

    use function Enobrev\dbg;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use Zend\Diactoros\Response\JsonResponse;

    use Enobrev\API\HTTP;
    use Enobrev\Log;

    /**
     * @package Enobrev\API\Middleware
     */
    class ResponseBuilderDone implements MiddlewareInterface {
        /**
         * Process a server request and return a response.
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oResponse = new JsonResponse(ResponseBuilder::get($oRequest)->all(), HTTP\OK);
            $sBody = null;
            try {
                $sBody = json_encode($oResponse->getPayload(), JSON_PARTIAL_OUTPUT_ON_ERROR);
            } catch (\Exception $e) {
                // Skip it
            }

            if (!$sBody) {
                $sBody = '{"log_error": "not_encoded"}';
            }

            Log::i('Enobrev.Middleware.ResponseBuilderDone', [
                '#response' => [
                    'status' => $oResponse->getStatusCode(),
                    'headers' => json_encode($oResponse->getHeaders()),
                ],
                'body'     => $sBody
            ]);

            return $oResponse;
        }
    }