<?php
    namespace Enobrev\API\Middleware;

    use Adbar\Dot;
    use BenMorel\OpenApiSchemaToJsonSchema\Convert;
    use cebe\openapi\spec\Schema as OpenApi_Schema;
    use Enobrev\API\OpenApiInterface;
    use JsonSchema\Constraints\Constraint;
    use JsonSchema\Validator;
    use Middlewares;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use ReflectionException;

    use Enobrev\API\Exception\ValidationException;
    use Enobrev\API\FullSpec\Component\Schema;
    use Enobrev\API\HTTP;
    use Enobrev\API\Middleware\Request\AttributeSpec;
    use Enobrev\API\Spec;
    use Enobrev\Log;
    use function Enobrev\dbg;

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
            $oPathParams = Convert::openapiSchemaToJsonSchema($oSpec->pathParamsToSchema()->getSerializableData());

            $oValidator  = new Validator;
            $oValidator->validate(
                $oParameters,
                $oPathParams,
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
            $oSpec        = AttributeSpec::getSpec($oRequest);
            $aParameters  = $oRequest->getQueryParams();
            $oParameters  = (object) $aParameters;
            $oQueryParams = Convert::openapiSchemaToJsonSchema($oSpec->queryParamsToSchema()->getSerializableData());
            $oValidator   = new Validator;
            $oValidator->validate(
                $oParameters,
                $oQueryParams,
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

            if ($oSpec->hasAPostBodyOneOfOrAnyOf()) {
                if ($oSpec->hasAPostBodyDiscriminator()) {
                    $oSchema     = $oSpec->getSchemaFromPostBodyDiscriminator($oParameters);
                    if ($oSchema instanceof OpenApiInterface === false) {
                        $sDiscriminator = $oSpec->getPostBodyDiscriminator();
                        $aContext = [
                            [
                                'property'   => $sDiscriminator,
                                'pointer'    => "/$sDiscriminator",
                                'message'    => 'Discriminator value did not match any available schemas',
                                'constraint' => 'discriminator',
                                'value'      => $oParameters->$sDiscriminator ?? null
                            ]
                        ];

                        Log::e('Enobrev.Middleware.ValidateSpec.validatePostParameters.InvalidDiscriminator', ['state' => 'PostBodySchemaSelector.Error', 'errors' => $aContext]);
                        throw ValidationException::create(HTTP\BAD_REQUEST, $aContext);
                    }

                    $oPostParams = Convert::openapiSchemaToJsonSchema($oSchema->getSpecObject()->getSerializableData());

                    $oValidator  = new Validator;
                    $oValidator->validate(
                        $oParameters,
                        $oPostParams,
                        Constraint::CHECK_MODE_APPLY_DEFAULTS
                    );

                    if ($oValidator->isValid() === false) {
                        Log::e('Enobrev.Middleware.ValidateSpec.validatePostParameters', ['state' => 'PostBodySchemaSelector.Error', 'errors' => $this->getErrorsWithValues($oValidator, $aParameters)]);
                        throw ValidationException::create(HTTP\BAD_REQUEST, $this->getErrorsWithValues($oValidator, $aParameters));
                    }
                } else {
                    // FIXME: The post body has a schema that allows one of many different combinations of post parameters - loop through and see if we match at least one

                    $aSchemas = $oSpec->getPostBodySchemas();
                    $bValid   = false;
                    $oError   = null;

                    /** @var Schema $oSchema */
                    foreach($aSchemas as $oSchema) {
                        $oPostParams = Convert::openapiSchemaToJsonSchema($oSchema->getSpecObject()->getSerializableData());

                        $oValidator  = new Validator;
                        $oValidator->validate(
                            $oParameters,
                            $oPostParams,
                            Constraint::CHECK_MODE_APPLY_DEFAULTS
                        );

                        if ($oValidator->isValid()) {
                            $bValid = true;
                        } else {
                            Log::e('Enobrev.Middleware.ValidateSpec.validatePostParameters', ['state' => 'PostBodyOneOf.Error', 'errors' => $this->getErrorsWithValues($oValidator, $aParameters)]);
                            $oError = ValidationException::create(HTTP\BAD_REQUEST, $this->getErrorsWithValues($oValidator, $aParameters));
                        }
                    }

                    if (!$bValid) {
                        throw $oError;
                    }
                }
            } else {
                $oPostParamSchema = $oSpec->getPostParamSchema();
                if ($oPostParamSchema instanceof OpenApi_Schema) {
                    $oPostParams = Convert::openapiSchemaToJsonSchema($oPostParamSchema->getSerializableData());

                    $oValidator  = new Validator;
                    $oValidator->validate(
                        $oParameters,
                        $oPostParams,
                        Constraint::CHECK_MODE_APPLY_DEFAULTS
                    );

                    if ($oValidator->isValid() === false) {
                        Log::e('Enobrev.Middleware.ValidateSpec.validatePostParameters', ['state' => 'Other.Error', 'errors' => $this->getErrorsWithValues($oValidator, $aParameters)]);
                        throw ValidationException::create(HTTP\BAD_REQUEST, $this->getErrorsWithValues($oValidator, $aParameters));
                    }
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
                if (empty($aError['property']) && is_array($aError['constraint']) && $aError['constraint']['name'] === 'additionalProp') {
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