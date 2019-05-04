<?php
    namespace Enobrev\API\Middleware\Response;

    use Adbar\Dot;

    use Enobrev\API\Exception;
    use Enobrev\API\SpecInterface;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\Middleware\ResponseBuilder;
    use Enobrev\API\RequestAttribute;
    use Enobrev\API\RequestAttributeInterface;
    use Enobrev\Log;

    class MetadataErrors implements MiddlewareInterface, RequestAttributeInterface {
        use RequestAttribute;

        const STATUS_PASS = 'PASS';
        const STATUS_FAIL = 'FAIL';

        private static function init(ServerRequestInterface $oRequest): ServerRequestInterface {
            $oErrors = self::getAttribute($oRequest);

            if (!$oErrors) {
                $oErrors = new Dot([
                    'validation' => [
                        'status' => 'PASS',
                        'errors' => []
                    ],
                    'processing' => [
                        'status' => 'PASS',
                        'errors' => []
                    ],
                    'server' => [
                        'status' => 'PASS',
                        'errors' => []
                    ]
                ]);
            }

            return self::setAttribute($oRequest, $oErrors);
        }

        public static function addValidationPass(ServerRequestInterface $oRequest): ServerRequestInterface {
            $oRequest = self::init($oRequest);
            $oErrors  = self::getAttribute($oRequest);
            $oErrors->set('validation.status', self::STATUS_PASS);
            return self::setAttribute($oRequest, $oErrors);
        }

        public static function addValidationFail(ServerRequestInterface $oRequest, array $aErrors): ServerRequestInterface {
            Log::setProcessIsError(true);
            $oRequest = self::init($oRequest);
            $oErrors  = self::getAttribute($oRequest);
            $oErrors->set('validation.status', self::STATUS_FAIL);
            $oErrors->set('validation.errors', $aErrors);
            return self::setAttribute($oRequest, $oErrors);
        }

        public static function addProcessingPass(ServerRequestInterface $oRequest): ServerRequestInterface {
            $oRequest = self::init($oRequest);
            $oErrors  = self::getAttribute($oRequest);
            $oErrors->set('processing.status',  self::STATUS_PASS);
            return self::setAttribute($oRequest, $oErrors);
        }

        /**
         * @param SpecInterface $oSpec
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @param int $iStatus
         * @return ResponseInterface
         * @throws Exception
         */
        public static function handleProcessingError(SpecInterface $oSpec, ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler, int $iStatus): ResponseInterface {
            Log::setProcessIsError(true);
            $oRequest = self::init($oRequest);
            $oErrors  = self::getAttribute($oRequest);
            $oErrors->set('processing.status',  self::STATUS_FAIL);
            $oErrors->push('processing.errors', $oSpec->spec()->getResponseDescription($iStatus));

            return $oHandler->handle($oRequest)->withStatus($iStatus);
        }

        public static function addProcessingError(ServerRequestInterface $oRequest, $mError): ServerRequestInterface {
            Log::setProcessIsError(true);
            $oRequest = self::init($oRequest);
            $oErrors  = self::getAttribute($oRequest);
            $oErrors->set('processing.status',  self::STATUS_FAIL);
            $oErrors->push('processing.errors', $mError);
            return self::setAttribute($oRequest, $oErrors);
        }

        public static function addServerError(ServerRequestInterface $oRequest, $mError): ServerRequestInterface {
            Log::setProcessIsError(true);
            $oRequest = self::init($oRequest);
            $oErrors  = self::getAttribute($oRequest);
            $oErrors->set('server.status',  self::STATUS_FAIL);
            $oErrors->push('server.errors', $mError);
            return self::setAttribute($oRequest, $oErrors);
        }

        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer = Log::startTimer('Enobrev.Middleware.MetadataErrors');
            $oBuilder = ResponseBuilder::get($oRequest);

            if ($oBuilder) {
                $oRequest = self::init($oRequest);
                $oErrors  = self::getAttribute($oRequest);

                if ($oErrors->get('validation.status') == self::STATUS_FAIL) {
                    $oBuilder->set('_errors', $oErrors->get('validation.errors'));
                }

                if ($oErrors->get('processing.status') == self::STATUS_FAIL) {
                    $oBuilder->set('_errors', $oErrors->get('processing.errors'));
                }

                if ($oErrors->get('server.status') == self::STATUS_FAIL) {
                    $oBuilder->set('_errors', $oErrors->get('server.errors'));
                }

                $oRequest= ResponseBuilder::update($oRequest, $oBuilder);
            }

            Log::dt($oTimer);
            return $oHandler->handle($oRequest);
        }
    }