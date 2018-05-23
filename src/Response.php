<?php
    namespace Enobrev\API;

    use DateTime;

    use Money\Money;
    use stdClass;

    use Enobrev\API\HTTP;
    use Enobrev\ORM\ModifiedDateColumn;
    use Enobrev\ORM\Field;
    use Enobrev\ORM\Table;
    use Enobrev\ORM\Tables;
    use Enobrev\Log;

    use function Enobrev\array_from_path;

    use Zend\Diactoros\Response as ZendResponse;

    class Response {
        const FORMAT_PNG       = 'png';
        const FORMAT_JPG       = 'jpg';
        const FORMAT_JPEG      = 'jpeg';
        const FORMAT_GIF       = 'gif';
        const FORMAT_TTF       = 'ttf';
        const FORMAT_WOFF      = 'woff';
        const FORMAT_CSS       = 'css';
        const FORMAT_JSON      = 'json';
        const FORMAT_CSV       = 'csv';
        const FORMAT_EMPTY     = 'empty';
        const FORMAT_HTML      = 'html';

        const SYNC_DATE_FORMAT = 'Y-m-d H:i:s';
        const HTTP_DATE_FORMAT = 'D, d M Y H:i:s T';

        /** @var stdClass */
        protected static $oGlobalServer;

        /** @var  string */
        protected $sFormat = null;

        /** @var  string */
        protected $sFile = null;

        /** @var  boolean */
        protected $bAsAttachment = null;

        /** @var  array */
        protected $aResponse = [];

        /** @var  Request */
        protected $Request = null;

        /** @var  string */
        protected $sTextOutput = '';

        /** @var  stdClass */
        protected $oOutput = null;

        /** @var  array */
        protected $aHeaders = [];

        /** @var  int */
        protected $iStatus = null;

        /** @var  bool */
        protected $bIncludeRequestInOutput = true;

        /** @var  bool */
        private $bHasResponded = false;

        /** @var array */
        protected static $aAllowedURIs = ['*'];

        /** @var string */
        protected static $sDomain = null;

        /** @var string */
        protected static $sScheme = 'https://';

        /**
         * Response constructor.
         * @param Request $oRequest
         * @throws Exception\Response
         */
        public function __construct(Request $oRequest) {
            if (self::$sDomain === null) {
                throw new Exception\Response('API Response Not Initialized');
            }

            $this->aHeaders = [];
            $this->includeRequestInOutput(true);
            $this->setRequest($oRequest);
            $this->setFormat($oRequest->Format);
            $this->setStatus(HTTP\OK);

            $this->oOutput = new stdClass();

            if (!self::$oGlobalServer) {
                self::$oGlobalServer = new stdClass;
            }
        }

        /**
         * @param Request $oRequest
         */
        private function setRequest(Request $oRequest): void {
            $this->Request = $oRequest;
        }

        /**
         * @return stdClass
         */
        private function getRequestOutput() {
            $oRequest = new stdClass();
            $oRequest->logs = new stdClass();
            $oRequest->logs->thread  = Log::getThreadHashForOutput();
            $oRequest->logs->request = Log::getRequestHashForOutput();

            $oRequest->method = $this->Request->OriginalRequest->getMethod();
            $oRequest->path   = $this->Request->OriginalRequest->getUri()->getPath();

            if ($this->bIncludeRequestInOutput) {
                $oRequest->attributes   = $this->Request->OriginalRequest->getAttributes();
                $oRequest->query        = $this->Request->OriginalRequest->getQueryParams();
                $oRequest->data         = $this->Request->POST;
            }

            return $oRequest;
        }

        /**
         * @param string $sDomain
         * @param string $sScheme
         * @param array  $aAllowedURIs
         */
        public static function init(string $sDomain, string $sScheme = 'https://', array $aAllowedURIs = ['*']): void {
            self::$sScheme      = $sScheme;
            self::$sDomain      = $sDomain;
            self::$aAllowedURIs = $aAllowedURIs;
        }

        /**
         * @param bool $bIncludeRequestInOutput
         */
        public function includeRequestInOutput(bool $bIncludeRequestInOutput): void {
            $this->bIncludeRequestInOutput = $bIncludeRequestInOutput;
        }

        /**
         * @param string $sFormat
         */
        public function setFormat(string $sFormat): void {
            $this->sFormat = $sFormat;
        }

        /**
         * @param string $sFile
         * @param bool $bAsAttachment
         */
        public function setFile(string $sFile, $bAsAttachment = false): void {
            $this->sFile         = $sFile;
            $this->bAsAttachment = $bAsAttachment;
        }

        /**
         * @param string $sText
         */
        public function setText(string $sText): void {
            $this->sTextOutput = $sText;
        }

        /**
         * @param int $iContentLength
         */
        public function setContentLength(int $iContentLength): void {
            $this->addHeader('Content-Length', $iContentLength);
        }

        /**
         * @param string $sContentType
         */
        public function setContentType(string $sContentType): void {
            $this->addHeader('Content-Type', $sContentType);
        }

        /**
         * @param array $aAllow
         */
        public function setAllow(Array $aAllow): void {
            $this->addHeader('Allow', implode(',', $aAllow));
        }

        /**
         * @param string $sETag
         */
        public function setEtag($sETag = null): void {
            if ($sETag) {
                $this->addHeader('ETag', $sETag);
            }
        }

        /**
         * @param DateTime $oLastModified
         */
        public function setLastModified(DateTime $oLastModified = null): void {
            if ($oLastModified instanceof DateTime) {
                $this->addHeader('Last-Modified', $oLastModified->format(self::HTTP_DATE_FORMAT));
            }
        }

        /**
         * @param ModifiedDateColumn[]|Tables $oTables
         * @psalm-suppress RawObjectIteration
         */
        public function setLastModifiedFromTables($oTables): void {
            $oLatest = new DateTime();
            $oLatest->modify('-10 years');
            foreach($oTables as $oTable) {
                if ($oTable instanceof ModifiedDateColumn) {
                    $oLatest = max($oLatest, $oTable->getLastModified());
                } else {
                    $this->setLastModified(new DateTime());
                    return;
                }
            }

            $this->setLastModified($oLatest);
        }

        /**
         * @param Table $oTable
         */
        public function setHeadersFromTable(Table $oTable): void {
            $this->setEtag($oTable->toHash());

            if ($oTable instanceof ModifiedDateColumn) {
                $this->setLastModified($oTable->getLastModified());
            }
        }

        /**
         * @param string $sHeader
         * @param mixed $sValue
         */
        public function addHeader($sHeader, $sValue): void {
            $this->aHeaders[$sHeader] = $sValue;
        }

        /**
         * @param mixed $sVar
         * @param mixed $mValue
         */
        public function add($sVar, $mValue = NULL): void {
            if ($sVar instanceof Table) {
                $this->add($sVar->getTitle(), $sVar->toArray());
            } else if ($sVar instanceof Field\DateTime) {
                $this->set($sVar->sColumn, (string) $sVar);
            } else if ($sVar instanceof Field) {
                $this->set($sVar->sColumn, $sVar->getValue());
            } else if (is_array($sVar)) {
                foreach ($sVar as $sKey => $sValue) {
                    if ($sValue instanceof Field) {
                        if (preg_match('/[a-zA-Z]/', $sKey)) { // Associative key - replacing field names
                            $this->set($sKey, $sValue);
                        } else {
                            $this->set($sValue->sColumn, $sValue);
                        }
                    } else {
                        $this->add($sKey, $sValue);
                    }
                }
            } else if ($mValue instanceof Table) {
                $aFields = $mValue->getFields();
                foreach($aFields as $oField) {
                    $this->set($sVar . '.' . $oField->sColumn, $oField);
                }
            } else if (is_array($mValue)) {
                foreach($mValue as $sKey => $sValue) {
                    if ($sValue instanceof Field) {
                        if (preg_match('/[a-zA-Z]/', $sKey)) { // Associative key - replacing field names
                            $this->set($sVar . '.' . $sKey, $sValue);
                        } else {
                            $this->set($sVar . '.' . $sValue->sColumn, $sValue);
                        }
                    } else {
                        $this->add($sVar . '.' . $sKey, $sValue);
                    }
                }
            } else {
                $this->set($sVar, $mValue);
            }
        }

        /**
         * @param string $sKey
         */
        public function remove(string $sKey): void {
            $aKey = explode('.', $sKey);
            $sTopKey = array_shift($aKey);

            if (!isset($this->oOutput->$sTopKey)) {
                return;
            }

            if (count($aKey) === 0) {
                unset($this->oOutput->$sTopKey);
            }

            $aTree = &$this->oOutput->$sTopKey;

            while (count($aKey) > 1) {
                $sKey = array_shift($aKey);

                if (!isset($aTree[$sKey])) {
                    return;
                }

                $aTree = &$aTree[$sKey];
            }

            $sKey = array_shift($aKey);
            unset($aTree[$sKey]);
        }

        /**
         * This is a workaround to ensure we can set _server values from within multi-requests.  I don't like it, but it gets the job done.
         * @param string $sKey
         * @param mixed  $mValue
         */
        public static function setGlobalServerData(string $sKey, $mValue): void {
            self::$oGlobalServer->$sKey = $mValue;
        }

        /**
         * @return stdClass
         */
        private function getServerDateInfo(): stdClass {
            if (self::$oGlobalServer && property_exists(self::$oGlobalServer, 'date')) {
                $oNow = self::$oGlobalServer->date;
            } else {
                $oNow = new \DateTime;
            }

            $oServerDate = new stdClass;
            $oServerDate->timezone      = $oNow->format('T');
            $oServerDate->timezone_gmt  = $oNow->format('P');
            $oServerDate->date          = $oNow->format(self::SYNC_DATE_FORMAT);
            $oServerDate->date_w3c      = $oNow->format(DateTime::W3C);

            return $oServerDate;
        }

        /**
         * @return stdClass
         */
        private function getServerOutput(): stdClass {
            if (self::$oGlobalServer) {
                return (object) array_merge((array) self::$oGlobalServer, (array) $this->getServerDateInfo());
            }

            return $this->getServerDateInfo();
        }

        /**
         * Turns a dot-separated var into a multidimensional array and merges it with prior data with
         * the same hierarchy
         *
         * @param string|array|Field $sVar
         * @param mixed $mValue
         * @return void
         */
        private function set($sVar, $mValue): void {
            if ($mValue instanceof Field\JSONText) {
                $mValue = json_decode($mValue->getValue());
            }

            if ($mValue instanceof Field) {
                $mValue = $mValue->getValue();
            }

            if ($mValue instanceof DateTime) {
                /** @var DateTime $mValue */
                // $mValue->setTimezone(new DateTimeZone('GMT')); - FIXME: should only be doing this by explicit request
                $mValue = $mValue->format(DateTime::RFC3339);
            }

            if ($mValue instanceof Money) {
                /** @var Money $mValue */
                $mValue = $mValue->getAmount();
            }

            /** @psalm-suppress PossiblyInvalidArgument */
            $aKey  = explode('.', $sVar);
            $sKey  = $aKey[0];
            $aData = array_from_path($sVar, $mValue);

            if(is_array($aData)) {
                if (!property_exists($this->oOutput, $sKey)) {
                    $this->oOutput->$sKey = array();
                }

                $aCleanData = array();
                foreach($aData as $sDataKey => $sDataValue) {
                    if ($sDataValue === NULL) {
                        continue;
                    }

                    $aCleanData[$sDataKey] = $sDataValue;
                }

                $this->oOutput->$sKey = array_replace_recursive($this->oOutput->$sKey, $aCleanData);
            } else if ($aData === NULL) {
                return;
            } else {
                $this->oOutput->$sKey = $aData;
            }
        }

        /**
         * Overrides all default output and replaces output object with $oOutput
         * @param array|stdClass $oOutput
         */
        public function overrideOutput($oOutput): void {
            $this->oOutput = $oOutput;
        }

        /**
         * @throws Exception\NoContentType
         */
        public function emptyResponse(): void {
            $this->setFormat(self::FORMAT_EMPTY);
            $this->respond();
        }

        /**
         * @param array ...$aMethods
         * @throws Exception\NoContentType
         */
        public function respondWithOptions(...$aMethods): void {
            $this->setAllow($aMethods);
            $this->statusNoContent();
            $this->respond();
        }

        /**
         * @return bool
         * @todo: Allow CORS headers to be overridden
         */
        private function setOrigin(): bool {
            $sHeaders = 'Authorization, Content-Type';
            $sMethods = implode(', ', Method\_ALL);

            if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], self::$aAllowedURIs)) {
                $this->addHeader('Access-Control-Allow-Origin',      $_SERVER['HTTP_ORIGIN']);
                $this->addHeader('Access-Control-Allow-Headers',     $sHeaders);
                $this->addHeader('Access-Control-Allow-Methods',     $sMethods);
                $this->addHeader('Access-Control-Allow-Credentials', 'true');
                return true;
            } else if (in_array('*', self::$aAllowedURIs)) {
                $this->addHeader('Access-Control-Allow-Origin',      '*');
                $this->addHeader('Access-Control-Allow-Headers',     $sHeaders);
                $this->addHeader('Access-Control-Allow-Methods',     $sMethods);
                $this->addHeader('Access-Control-Allow-Credentials', 'false');
                return true;
            }

            return false;
        }

        /**
         * @throws Exception\NoContentType
         */
        public function respond(): void {
            if ($this->bHasResponded) {
                Log::d('API.Response.respond.Duplicate');
                return;
            }

            $bAccessControlHeaders = $this->setOrigin();
            $oOutput               = $this->getOutput();

            Log::i('API.Response.respond', [
                '#ach'     => $bAccessControlHeaders,
                '#status'  => $this->iStatus,
                '#headers' => json_encode($this->aHeaders),
                'body'     => json_encode($oOutput)
            ]);

            if ($this->sFile) {
                if (!isset($this->aHeaders['Content-Type'])) {
                    throw new Exception\NoContentType('Missing Content Type');
                }

                if ($this->bAsAttachment) {
                    $oResponse = new ZendAttachmentResponse($this->sFile, $this->iStatus, $this->aHeaders);
                } else {
                    $oResponse = new ZendFileResponse($this->sFile, $this->iStatus, $this->aHeaders);
                }
            } else {
                switch($this->sFormat) {
                    default:
                    case self::FORMAT_JSON:
                        $oResponse = new ZendResponse\JsonResponse($oOutput, $this->iStatus, $this->aHeaders);
                        break;

                    case self::FORMAT_CSS:
                    case self::FORMAT_CSV:
                        if ($this->sTextOutput) {
                            $oResponse = new ZendResponse\TextResponse($this->sTextOutput, $this->iStatus, $this->aHeaders);
                        } else {
                            $oResponse = new ZendResponse\EmptyResponse($this->iStatus, $this->aHeaders);
                        }
                        break;

                    case self::FORMAT_EMPTY:
                        $oResponse = new ZendResponse\EmptyResponse($this->iStatus, $this->aHeaders);
                        break;

                    case self::FORMAT_HTML:
                        if ($this->sTextOutput) {
                            $oResponse = new ZendResponse\HtmlResponse($this->sTextOutput, $this->iStatus, $this->aHeaders);
                        } else {
                            $oResponse = new ZendResponse\EmptyResponse($this->iStatus, $this->aHeaders);
                        }
                        break;
                }
            }

            $oEmitter = new ZendResponse\SapiEmitter();
            $oEmitter->emit($oResponse);

            Log::justAddContext(['#size' => $oResponse->getBody()->getSize()]);

            $this->bHasResponded = true;
        }

        /**
         * @return stdClass
         */
        public function getOutput(): stdClass {
            $oOutput = $this->oOutput;

            if ($oOutput instanceof stdClass) {
                $oOutput->_server  = $this->getServerOutput();
                $oOutput->_request = $this->getRequestOutput();
            }

            return $oOutput;
        }

        /**
         * @return stdClass
         */
        public function toObject(): stdClass {
            $oOutput = new stdClass();
            $oOutput->headers   = $this->aHeaders;
            $oOutput->status    = $this->iStatus;
            $oOutput->data      = $this->getOutput();

            return $oOutput;
        }

        /**
         * @param string $sName
         * @param string $sValue
         * @param int $iHours
         */
        public function addCookie($sName, $sValue, $iHours = 1): void {
            setcookie($sName, $sValue, time() + (3600 * $iHours), '/', self::$sDomain, self::$sScheme== 'https://', false);
        }

        /**
         * @param string $sUri
         * @param int $iStatus
         */
        public function redirect($sUri, $iStatus = HTTP\FOUND): void {
            (new ZendResponse\SapiEmitter())->emit(new ZendResponse\RedirectResponse($sUri, $iStatus, $this->aHeaders));
            exit(0);
        }

        /**
         * @return bool
         */
        public function isStatusFailing(): bool {
            return $this->iStatus >= HTTP\BAD_REQUEST;
        }

        /**
         * @param int $iStatus
         */
        public function setStatus(int $iStatus): void {
            $this->iStatus = $iStatus;
        }

        public function statusNoContent(): void {
            $this->setStatus(HTTP\NO_CONTENT);
            $this->setFormat(self::FORMAT_EMPTY);
        }

        public function statusBadRequest(): void {
            Log::setProcessIsError(true);
            $this->setStatus(HTTP\BAD_REQUEST);
            //$this->setFormat(self::FORMAT_EMPTY);
        }

        public function statusInternalServerError(): void {
            Log::setProcessIsError(true);
            $this->setStatus(HTTP\INTERNAL_SERVER_ERROR);
            //$this->setFormat(self::FORMAT_EMPTY);
        }

        public function statusUnauthorized(): void {
            Log::setProcessIsError(true);
            $this->setStatus(HTTP\UNAUTHORIZED);
            //$this->setFormat(self::FORMAT_EMPTY);
        }

        public function statusForbidden(): void {
            Log::setProcessIsError(true);
            $this->setStatus(HTTP\FORBIDDEN);
            //$this->setFormat(self::FORMAT_EMPTY);
        }

        public function statusNotFound(): void {
            Log::setProcessIsError(true);
            $this->setStatus(HTTP\NOT_FOUND);
            //$this->setFormat(self::FORMAT_EMPTY);
        }

        public function statusMethodNotAllowed(): void {
            Log::setProcessIsError(true);
            $this->setStatus(HTTP\METHOD_NOT_ALLOWED);
            //$this->setFormat(self::FORMAT_EMPTY);
        }
    }