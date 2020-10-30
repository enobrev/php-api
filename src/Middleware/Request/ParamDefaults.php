<?php
    namespace Enobrev\API\Middleware\Request;

    use stdClass;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\Log;
    use Enobrev\API\Middleware\FastRoute;
    use Enobrev\API\Param;
    use Enobrev\API\Spec;

    class ParamDefaults implements MiddlewareInterface {

        /**
         * @param ServerRequestInterface  $oRequest
         * @param RequestHandlerInterface $oHandler
         *
         * @return ResponseInterface
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer = Log::startTimer('Enobrev.Middleware.CoerceParams');
            $oSpec = AttributeSpec::getSpec($oRequest);

            if ($oSpec instanceof Spec === false) {
                Log::dt($oTimer);
                return $oHandler->handle($oRequest);
            }

            $aCoerced = [
                'path'   => [],
                'query'  => [],
                'post'   => [],
                'header' => []
            ];

            $aPathParams = FastRoute::getPathParams($oRequest) ?? [];
            foreach ($oSpec->getPathParams() as $sParam => $oParam) {
                if (!isset($aPathParams[$sParam]) && $oParam->hasDefault()) {
                    $aPathParams[$sParam] = $oParam->getDefault();
                    $aCoerced['path'][] = $sParam;
                }
            }

            if (count($aCoerced['path'])) {
                $oRequest = FastRoute::updatePathParams($oRequest, $aPathParams);
            }

            $aQueryParams = $oRequest->getQueryParams() ?? [];
            foreach ($oSpec->getQueryParams() as $sParam => $oParam) {
                if (!isset($aQueryParams[$sParam]) && $oParam->hasDefault()) {
                    /** @noinspection ProperNullCoalescingOperatorUsageInspection */
                    $aQueryParams[$sParam] = $oParam->getDefault() ?? null;
                    $aCoerced['query'][]   = $sParam;
                }
            }

            if (count($aCoerced['query'])) {
                $oRequest = $oRequest->withQueryParams($aQueryParams);
            }

            $aPostParams = $oRequest->getParsedBody() ?? [];
            foreach ($oSpec->resolvePostParams() as $sParam => $oParam) {
                if ($oParam instanceof Param\_Object) {
                    $aRevisedPostParams = $this->coerceObject($sParam, $oParam, $aPostParams);
                    if ($aPostParams !== $aRevisedPostParams) {
                        $aPostParams = $aRevisedPostParams;
                        $aCoerced['post'][] = $sParam;
                    }
                } else if (!isset($aPostParams[$sParam]) && $oParam->hasDefault()) {
                    /** @noinspection ProperNullCoalescingOperatorUsageInspection */
                    $aPostParams[$sParam] = $oParam->getDefault() ?? null;
                    $aCoerced['post'][]   = $sParam;
                }
            }

            if (count($aCoerced['post'])) {
                $oRequest = $oRequest->withParsedBody($aPostParams);
            }

            $aHeaderParams = $oRequest->getHeaders() ?? [];
            foreach ($oSpec->getHeaderParams() as $sParam => $oParam) {
                if (!isset($aHeaderParams[$sParam]) && $oParam->hasDefault()) {
                    $oRequest = $oRequest->withHeader($sParam, $oParam->getDefault());
                    $aCoerced['header'][] = $sParam;
                }
            }

            Log::dt($oTimer, ['coerced' => $aCoerced]);
            return $oHandler->handle($oRequest);
        }

        /**
         * @param string         $sParam
         * @param Param\_Object  $oParam
         * @param array|stdClass $mRequestParams
         *
         * @return array|stdClass
         */
        private function coerceObject(string $sParam, Param\_Object $oParam, $mRequestParams) {
            $bCoerced = false;
            if ($oParam->hasItems()) {
                if ($mRequestParams instanceof stdClass) {
                    $oCoerced = $mRequestParams->$sParam ?? new stdClass();
                } else {
                    $oCoerced = $mRequestParams[$sParam] ?? new stdClass();
                }

                /** @var Param $oSubParam */
                foreach($oParam->getItems() as $sSubParam => $oSubParam) {
                    if (!property_exists($oCoerced, $sSubParam)) {
                        if ($oSubParam instanceof Param\_Object) {
                            $oCoerced->$sSubParam = $this->coerceObject($sSubParam, $oSubParam, []);
                            $bCoerced = true;
                        } else if ($oSubParam->hasDefault()) {
                            /** @noinspection ProperNullCoalescingOperatorUsageInspection */
                            $oCoerced->$sSubParam = $oSubParam->getDefault() ?? null;
                            $bCoerced = true;
                        }
                    } else if ($oSubParam instanceof Param\_Object) {
                        $oCoerced->$sSubParam = $this->coerceObject($sSubParam, $oSubParam, $oCoerced->$sSubParam);
                        $bCoerced = true;
                    }
                }

                if ($bCoerced) {
                    if ($mRequestParams instanceof stdClass) {
                        $mRequestParams->$sParam = $oCoerced;
                    } else {
                        $mRequestParams[$sParam] = $oCoerced;
                    }
                }
            }

            return $mRequestParams;
        }
    }