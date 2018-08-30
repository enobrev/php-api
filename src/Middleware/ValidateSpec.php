<?php
    namespace Enobrev\API\Middleware;

    use Adbar\Dot;
    use JsonSchema\Constraints\Constraint;
    use JsonSchema\Validator;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use Zend\Diactoros\Response\JsonResponse;

    use Enobrev\API\HTTP;
    use Enobrev\API\Spec;
    use Enobrev\API\SpecInterface;

    use function Enobrev\dbg;


    class ValidateSpec implements MiddlewareInterface {

        private $bValid = true;

        /**
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $sClass = $oRequest->getAttribute(FastRoute::ATTRIBUTE_REQUEST_HANDLER);

            if (empty($sClass)) {
                return $oHandler->handle($oRequest);
            }

            /** @var SpecInterface $oClass */
            $oClass = new $sClass;

            if ($oClass instanceof SpecInterface === false) {
                return $oHandler->handle($oRequest);
            }

            $oSpec    = $oClass->spec();

            $oBuilder = $oRequest->getAttribute(ResponseBuilder::class, new Dot());
            $oBuilder = $this->validateParameters($oSpec->PathParams,  $oRequest->getAttribute(FastRoute::ATTRIBUTE_PATH_PARAMS),  $oBuilder);
            $oBuilder = $this->validateParameters($oSpec->QueryParams, $oRequest->getQueryParams(),                                $oBuilder); // FIXME: Needs to be POST params as well

            if (!$this->bValid) {
                //$oBuilder->set('spec', $oSpec->toArray());
                return new JsonResponse($oBuilder->all(), HTTP\BAD_REQUEST);
            }

            return $oHandler->handle($oRequest);
        }

        /**
         * @param array $aSpecParameters
         * @param array $aParameters
         * @param Dot $oPayload
         * @return Dot
         */
        private function validateParameters(array $aSpecParameters, array $aParameters, Dot $oPayload) {
            $oParameters = (object) $aParameters;
            $oValidator  = new Validator;
            $oValidator->validate(
                $oParameters,
                Spec::paramsToJsonSchema($aSpecParameters)->all(),
                Constraint::CHECK_MODE_APPLY_DEFAULTS | Constraint::CHECK_MODE_ONLY_REQUIRED_DEFAULTS | Constraint::CHECK_MODE_COERCE_TYPES
            );

            if (!$oValidator->isValid()) {
                $oDot = new Dot($aParameters);

                $aErrors = [];
                foreach($oValidator->getErrors() as $aError) {
                    // convert from array property `param[index]` to `param.index`
                    $sProperty = str_replace('[', '.', $aError['property']);
                    $sProperty = str_replace(']', '',  $sProperty);

                    $aError['value'] = $oDot->get($sProperty);
                    $aErrors[]       = $aError;
                }

                $oPayload->set('_request.validation.status', 'FAIL');
                $oPayload->set('_request.validation.errors', $aErrors);

                $this->bValid = false;
            } else if ($this->bValid) {
                $oPayload->set('_request.validation.status', 'PASS');
            }

            return $oPayload;
        }
    }