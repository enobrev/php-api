<?php
    namespace Enobrev\API;

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
        public $Headers;

        /** @var  string */
        public $Method = Method\GET;

        /** @var  array */
        public $GET;

        /** @var  array */
        public $POST;

        /** @var  array */
        public $PUT;

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

            Log::d('API.REQUEST', (array) $this);
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
            } else if ($aHeader && $aHeader[0]) {
                new Stream($aPut, $aHeader[0]);
                $aPut = $aPut['post'] ?? [];
            } else {
                new Stream($aPut);
                $aPut = $aPut['post'] ?? [];
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
                $aPost     = array_merge($aPost, json_decode($sContents, true));
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
         * @param array ...$aParams
         * @throws \Exception
         */
        public function required($sMethod, ...$aParams) {
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
         * @param string, $sParam
         * @param mixed|null $sDefault
         * @return mixed|null
         */
        public function param($sParam, $sDefault = null) {
            return $this->OriginalRequest->getAttribute($sParam, $sDefault);
        }

        /**
         * @param array $aParams
         */
        public function updateParams(array $aParams) {
            foreach($aParams as $sKey => $sValue) {
                $this->updateParam($sKey, $sValue);
            }
        }

        /**
         * @param string $sKey
         * @param string $sValue
         */
        public function updateParam($sKey, $sValue) {
            $this->OriginalRequest = $this->OriginalRequest->withAttribute($sKey, $sValue);
        }

        /**
         * Adds Version to Beginning of Path Array if it's not already there
         */
        private function ensurePathVersion() {
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
        private function splitPath() {
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
        private function parseFormatFromPath(string $sFormat = Response::FORMAT_JSON) {
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