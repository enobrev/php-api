<?php
    namespace Enobrev\API\Middleware;

    use Adbar\Dot;
    use JsonSchema\Constraints\Constraint;
    use JsonSchema\Validator;
    use Middlewares;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\Exception\ValidationException;
    use Enobrev\API\HTTP;
    use Enobrev\API\Middleware\Request\AttributeSpec;
    use Enobrev\API\Spec;
    use Enobrev\Log;

    use function Enobrev\dbg;

    class ValidateSpec implements MiddlewareInterface {
        /**
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         * @throws Middlewares\Utils\HttpErrorException
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer = Log::startTimer('Enobrev.Middleware.ValidateSpec');
            $oSpec = AttributeSpec::getSpec($oRequest);

            if ($oSpec instanceof Spec === false) {
                Log::dt($oTimer);
                return $oHandler->handle($oRequest);
            }

            $oRequest = $this->validatePathParameters($oRequest);
            $oRequest = $this->validateQueryParameters($oRequest);
            $oRequest = $this->validatePostParameters($oRequest);

            Log::dt($oTimer);
            return $oHandler->handle($oRequest);
        }

        /**
         * @param ServerRequestInterface $oRequest
         * @return ServerRequestInterface
         * @throws Middlewares\Utils\HttpErrorException
         */
        private function validatePathParameters(ServerRequestInterface $oRequest): ServerRequestInterface {
            $oSpec       = AttributeSpec::getSpec($oRequest);
            $aParameters = FastRoute::getPathParams($oRequest);
            $oParameters = (object) $aParameters;
            $oValidator  = new Validator;
            $oValidator->validate(
                $oParameters,
                $oSpec->pathParamsToJsonSchema(),
                Constraint::CHECK_MODE_APPLY_DEFAULTS | Constraint::CHECK_MODE_COERCE_TYPES
            );

            if ($oValidator->isValid() === false) {
                throw ValidationException::create(HTTP\BAD_REQUEST, $this->getErrorsWithValues($oValidator, $aParameters));
            }

            $oRequest = FastRoute::updatePathParams($oRequest, (array) $oParameters);

            return $oRequest;
        }

        /**
         * @param ServerRequestInterface $oRequest
         * @return ServerRequestInterface
         * @throws Middlewares\Utils\HttpErrorException
         */
        private function validateQueryParameters(ServerRequestInterface $oRequest): ServerRequestInterface {
            $oSpec       = AttributeSpec::getSpec($oRequest);
            $aParameters = $oRequest->getQueryParams();
            $oParameters = (object) $aParameters;
            $oValidator  = new Validator;
            $oValidator->validate(
                $oParameters,
                $oSpec->queryParamsToJsonSchema(),
                Constraint::CHECK_MODE_APPLY_DEFAULTS | Constraint::CHECK_MODE_COERCE_TYPES
            );

            if ($oValidator->isValid() === false) {
                throw ValidationException::create(HTTP\BAD_REQUEST, $this->getErrorsWithValues($oValidator, $aParameters));
            }

            $oRequest = $oRequest->withQueryParams((array) $oParameters);

            return $oRequest;
        }

        /**
         * @param ServerRequestInterface $oRequest
         * @return ServerRequestInterface
         * @throws Middlewares\Utils\HttpErrorException
         */
        private function validatePostParameters(ServerRequestInterface $oRequest): ServerRequestInterface {
            $oSpec       = AttributeSpec::getSpec($oRequest);
            $aParameters = $oRequest->getParsedBody();
            $oParameters = (object) $aParameters;

            $oValidator  = new Validator;
            $oValidator->validate(
                $oParameters,
                $oSpec->postParamsToJsonSchema(),
                Constraint::CHECK_MODE_APPLY_DEFAULTS | Constraint::CHECK_MODE_COERCE_TYPES
            );

            if ($oValidator->isValid() === false) {
                throw ValidationException::create(HTTP\BAD_REQUEST, $this->getErrorsWithValues($oValidator, $aParameters));
            }

            $oRequest = $oRequest->withParsedBody((array) $oParameters);

            return $oRequest;
        }

        private function getErrorsWithValues(Validator $oValidator, ?array $aParameters): ?array {
            if ($oValidator->isValid()) {
                return null;
            }

            $aErrors = [];
            $oParameters = new Dot($aParameters);

            foreach ($oValidator->getErrors() as $aError) {
                if (empty($aError['property']) && $aError['constraint']['name'] == 'additionalProp') {
                    $aError['property'] = $aError['constraint']['params']['property'];
                    $aError['value'] = $oParameters->get($aError['property']);
                } else {
                    // convert from array property `param[index]` to `param.index`
                    $sProperty = str_replace('[', '.', $aError['property']);
                    $sProperty = str_replace(']', '', $sProperty);
                    $aError['value'] = $oParameters->get($sProperty);
                }

                $aErrors[] = $aError;
            }

            return $aErrors;
        }
    }