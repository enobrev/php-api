<?php
    namespace Enobrev\API;

    use function Enobrev\dbg;
    use Zend\Diactoros\ServerRequest;
    use Enobrev\Log;
    use Enobrev\API\Exception;

    class Request {
        /** @var  ServerRequest */
        public $OriginalRequest;

        /** @var array  */
        public $Path;

        /** @var string  */
        public $Format;

        /** @var  array */
        public $Headers = [];

        /** @var  string */
        public $Method = Method\GET;

        /** @var  array */
        public $GET  = null;

        /** @var  array */
        public $POST = null;

        /** @var  array */
        public $PUT  = null;

        /** @var array */
        public $ValidatedParams = [];

        /** @var array */
        public $QueryParams = [];

        /** @var array */
        public $PathParams = [];

        public function __construct(ServerRequest $oRequest) {
            $this->OriginalRequest  = $oRequest;
            $this->Path             = $this->splitPath();
            $this->Format           = $this->parseFormatFromPath();
            $this->Method           = $this->OriginalRequest->getMethod();

            $this->ensurePathVersion();

            $this->GET              = $this->OriginalRequest->getQueryParams();

            switch ($this->Method) {
                case Method\POST:  $this->POST = $this->handlePost(); break;
                case Method\PUT:   $this->PUT  = $this->handlePut();  break;
            }

            Log::i('API.Request.init', [
                '#request' => [
                    'method'     => $this->Method,
                    'path'       => implode('/', $this->Path),
                    'format'     => $this->Format,
                    'attributes' => json_encode($this->OriginalRequest->getAttributes()),
                    'query'      => json_encode($this->OriginalRequest->getQueryParams()),
                    'data'       => [
                        'GET'  => json_encode($this->GET  ?? []),
                        'POST' => json_encode($this->POST ?? []),
                        'PUT'  => json_encode($this->PUT  ?? [])
                    ]
                ]
            ]);
        }

        /**
         * Checks the contents of the put request for data, and if it's not there, uses raw input stream instead
         * @return array
         */
        protected function handlePut() {
            // TODO: Not sure if this belongs here - but it works here for now
            $aPut    = [];
            $aHeader = $this->OriginalRequest->getHeader('Content-Type');
            if ($aHeader && $aHeader[0] == 'application/x-www-form-urlencoded') {
                $sContents = $this->OriginalRequest->getBody()->getContents() ?: (string) $this->OriginalRequest->getBody();
                parse_str($sContents, $aPut);
            } else if ($aHeader && $aHeader[0] == 'application/json') {
                $sContents = $this->OriginalRequest->getBody()->getContents() ?: (string)$this->OriginalRequest->getBody();
                $aContents = json_decode($sContents, true);
                if ($aContents && is_array($aContents)) {
                    $aPut = array_merge($aPut, $aContents);
                }
            } else if ($aHeader && $aHeader[0]) {
                Log::w('API.Request.handlePut.NotHandled');
                /*
                new Stream($aPut, $aHeader[0]);
                $aPut = $aPut['post'] ?? [];
                */
            } else {
                Log::w('API.Request.handlePut.NotHandled');
                /*
                new Stream($aPut);
                $aPut = $aPut['post'] ?? [];
                */
            }

            if (isset($aPut['__json'])) {
                $aPut = array_merge($aPut, json_decode($aPut['__json'], true));
            }

            return $aPut;
        }

        /**
         * @return array
         */
        protected function handlePost() {
            $aPost   = $this->OriginalRequest->getParsedBody() ?: [];

            $aHeader = $this->OriginalRequest->getHeader('Content-Type');
            if ($aHeader && $aHeader[0] == 'application/json') {
                $sContents = $this->OriginalRequest->getBody()->getContents() ?: (string) $this->OriginalRequest->getBody();
                $aContents = json_decode($sContents, true);
                if ($aContents && is_array($aContents)) {
                    $aPost = array_merge($aPost, $aContents);
                }
            }

            if (isset($aPost['__json'])) {
                $aPost = array_merge($aPost, json_decode($aPost['__json'], true));
            }

            return $aPost;
        }

        /**
         * @return bool
         */
        public function pathIsRoot() {
            if (count($this->Path) == 1) {
                return Route::isVersion($this->Path[0]);
            }

            return false;
        }

        /**
         * @param string $sMethod
         * @param array  ...$aParams
         * @throws Exception\InvalidMethod
         * @throws Exception\MissingRequiredParameter
         */
        public function required(string $sMethod, ...$aParams): void {
            if (in_array($sMethod, Method\_ALL) === false) {
                throw new Exception\InvalidMethod('Invalid Method');
            }

            if (count($aParams)) {
                if (is_array($aParams[0])) {
                    $aParams = $aParams[0];
                }
            }

            foreach($aParams as $sParam) {
                if (!isset($this->$sMethod[$sParam])) {
                    throw new Exception\MissingRequiredParameter($sParam);
                }
            }
        }

        /**
         * @param string $sParam
         * @param null   $sDefault
         * @return mixed|null
         */
        public function GETParam(string $sParam, $sDefault = null) {
            return $this->param(Method\GET, $sParam, $sDefault);
        }

        /**
         * @param string $sParam
         * @param null   $sDefault
         * @return mixed|null
         */
        public function POSTParam(string $sParam, $sDefault = null) {
            return $this->param(Method\POST, $sParam, $sDefault);
        }

        /**
         * @param string     $sMethod
         * @param string     $sParam
         * @param null|mixed $sDefault
         * @return null|mixed
         */
        private function param(string $sMethod, string $sParam, $sDefault = null) {
            $mValue = $this->$sMethod[$sParam] ?? $sDefault;
            if (is_numeric($mValue)) {
                return $mValue + 0;
            }

            return $mValue;
        }

        /**
         * @return array|null
         */
        public function queryParams() :?array {
            if (!$this->QueryParams) {
                $aParams = [];

                if ($this->isPost()) {
                    $aParams = $this->handlePost();
                } else if ($this->isGet()) {
                    $aParams = $this->OriginalRequest->getQueryParams();
                }


                foreach ($aParams as &$mParam) {
                    if (strlen($mParam) === 0) {
                        continue;
                    } else if (is_numeric($mParam)) {
                        $mParam = $mParam + 0;
                    } else {
                        $mBool = filter_var($mParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        if ($mBool !== null) {
                            $mParam = $mBool;
                        }
                    }
                }

                $this->QueryParams = $aParams;
            }

            return $this->QueryParams;
        }

        public function queryParam(string $sParam, $sDefault = null) {
            $aParams = $this->queryParams();
            return $aParams[$sParam] ?? $sDefault;
        }

        /**
         * @return array
         */
        public function pathParams() {
            return $this->OriginalRequest->getAttributes();
        }

        /**
         * @param string     $sParam
         * @param mixed|null $sDefault
         * @return mixed
         */
        public function pathParam(string $sParam, $sDefault = null) {
            return $this->OriginalRequest->getAttribute($sParam, $sDefault);
        }

        /**
         * @param string     $sParam
         * @param mixed|null $sDefault
         * @return mixed
         */
        public function paramFromUriPath(string $sParam, $sDefault = null) {
            return $this->OriginalRequest->getAttribute($sParam, $sDefault);
        }

        /**
         * @param array $aParams
         */
        public function updatePathParams(array $aParams): void {
            foreach($aParams as $sKey => $sValue) {
                $this->updatePathParam($sKey, $sValue);
            }
        }

        /**
         * @param string $sKey
         * @param string $sValue
         */
        public function updatePathParam($sKey, $sValue): void {
            $this->OriginalRequest = $this->OriginalRequest->withAttribute($sKey, $sValue);
        }

        /**
         * @return array
         */
        public function getPathPairs(): array {
            $aPath      = $this->Path;
            $sVersion   = array_shift($aPath); // not using version, but still need to shift
            return array_chunk($aPath, 2);
        }

        /**
         * Adds Version to Beginning of Path Array if it's not already there
         */
        private function ensurePathVersion(): void {
            if (count($this->Path) == 0
            ||  Route::isVersion($this->Path[0]) === false) {
                $sVersion = Route::defaultVersion();
            } else {
                $sVersion = array_shift($this->Path);
            }

            array_unshift($this->Path, $sVersion);
        }

        /**
         * @return array
         */
        private function splitPath(): array {
            $sPath = trim($this->OriginalRequest->getUri()->getPath(), '/');
            if (strlen($sPath) == 0) {
                return [];
            }

            return explode('/', $sPath);
        }

        /**
         * @param string $sFormat
         * @return string
         */
        private function parseFormatFromPath(string $sFormat = Response::FORMAT_JSON): string {
            if (count($this->Path) > 0) {
                $sLast = array_pop($this->Path);
                if (strpos($sLast, '.') !== false) {
                    $aLast = explode('.', $sLast);
                    $this->Path[] = array_shift($aLast);
                    $sFormat = implode('.', $aLast);
                } else {
                    $this->Path[] = $sLast;
                }
            }

            return $sFormat;
        }

        /**
         * @return bool
         */
        public function isPost(): bool {
            return strtoupper($this->Method) == Method\POST;
        }

        /**
         * @return bool
         */
        public function isGet(): bool {
            return strtoupper($this->Method) == Method\GET;
        }

        /**
         * @return bool
         */
        public function isPut(): bool {
            return strtoupper($this->Method) == Method\PUT;
        }

        /**
         * @return bool
         */
        public function isOptions(): bool {
            return strtoupper($this->Method) == Method\OPTIONS;
        }
    }