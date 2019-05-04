<?php
    namespace Enobrev\API\Middleware;

    use Adbar\Dot;
    use Enobrev\API\FullSpec\Component\Schema;
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
    use ReflectionException;

    class ValidateSpec implements MiddlewareInterface {
        /**
         * @param ServerRequestInterface  $oRequest
         * @param RequestHandlerInterface $oHandler
         *
         * @return ResponseInterface
         * @throws Middlewares\Utils\HttpErrorException
         * @throws ReflectionException
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
                Constraint::CHECK_MODE_APPLY_DEFAULTS
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
                Constraint::CHECK_MODE_APPLY_DEFAULTS
            );

            if ($oValidator->isValid() === false) {
                throw ValidationException::create(HTTP\BAD_REQUEST, $this->getErrorsWithValues($oValidator, $aParameters));
            }

            $oRequest = $oRequest->withQueryParams((array) $oParameters);

            return $oRequest;
        }

        /**
         * @param ServerRequestInterface $oRequest
         *
         * @return ServerRequestInterface
         * @throws Middlewares\Utils\HttpErrorException
         * @throws ReflectionException
         */
        private function validatePostParameters(ServerRequestInterface $oRequest): ServerRequestInterface {
            $oSpec       = AttributeSpec::getSpec($oRequest);
            $aParameters = $oRequest->getParsedBody();
            $oParameters = (object) $aParameters;

            if ($oSpec->hasAPostBodyOneOf()) {
                if ($oSpec->hasPostBodySchemaSelector()) {
                    // FIXME: This is such a hackish workaround it's a bit embarassing.  The issue is that while using a OneOf schema for our post body
                    //  is great for documentation, it's crap for validation.  Our validation lib has no means of intelligently picking which "oneOf" schema
                    //  to use.  So this is basically an injected callback that does it for us.  I don't recommend using this method elsewhere without
                    //  significant contemplation, foresight, and proper fiber in your diet
                    $oValidator  = new Validator;
                    $oValidator->validate(
                        $oParameters,
                        $oSpec->getSchemaFromSelector($oParameters),
                        Constraint::CHECK_MODE_APPLY_DEFAULTS
                    );

                    if ($oValidator->isValid() === false) {
                        throw ValidationException::create(HTTP\BAD_REQUEST, $this->getErrorsWithValues($oValidator, $aParameters));
                    }
                } else {
                    // FIXME: The post body has a schema that allows one of many different combinations of post parameters - loop through and see if we match at least one
                    $aSchemas = $oSpec->getPostBodySchemas();
                    $bValid   = false;
                    $oError   = null;

                    /** @var Schema $oSchema */
                    foreach($aSchemas as $oSchema) {
                        $oValidator  = new Validator;
                        $oValidator->validate(
                            $oParameters,
                            $oSchema->getOpenAPI(),
                            Constraint::CHECK_MODE_APPLY_DEFAULTS
                        );

                        if ($oValidator->isValid()) {
                            $bValid = true;
                        } else {
                            $oError = ValidationException::create(HTTP\BAD_REQUEST, $this->getErrorsWithValues($oValidator, $aParameters));
                        }
                    }

                    if (!$bValid) {
                        throw $oError;
                    }
                }
            } else {
                $oValidator  = new Validator;
                $oValidator->validate(
                    $oParameters,
                    $oSpec->postParamsToJsonSchema(),
                    Constraint::CHECK_MODE_APPLY_DEFAULTS
                );

                if ($oValidator->isValid() === false) {
                    throw ValidationException::create(HTTP\BAD_REQUEST, $this->getErrorsWithValues($oValidator, $aParameters));
                }
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

            $aErrorProperties = [];

            foreach ($oValidator->getErrors() as $aError) {
                if (empty($aError['property']) && $aError['constraint']['name'] === 'additionalProp') {
                    $aError['property'] = $aError['constraint']['params']['property'];
                    $aError['value']    = $oParameters->get($aError['property']);
                } else {
                    // convert from array property `param[index]` to `param.index`
                    $sProperty       = str_replace(['[', ']'], ['.', ''], $aError['property']);
                    $aError['value'] = $oParameters->get($sProperty);
                }

                // only one error per property
                if (isset($aError['property'])) {
                    if (isset($aErrorProperties[$aError['property']])) {
                        if (
                            (
                                is_array($aError['constraint']) && isset($aError['constraint']['name']) && in_array($aError['constraint']['name'], ['type', 'anyOf'])
                            )
                        ||  in_array($aError['constraint'], ['type', 'anyOf'])) {
                            // An error on a nullable field , like lets say a maxLength error on a nullable field
                            // Will add two additional errors - one because the value is not null, and one because
                            // The field isn't matching either of the "anyOf" (which includes the original and the null
                            // This way we skip those extra errors as they're not useful.
                            continue;
                        }
                    }

                    $aErrorProperties[$aError['property']] = 1;
                }

                $aErrors[] = $aError;
            }

            return $aErrors;
        }
    }