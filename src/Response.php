<?php
    namespace Enobrev\API;

    use DateTime;

    use Enobrev\API\Exception\DocumentationException;
    use Enobrev\API\Exception\InvalidRequest;
    use JsonSchema\Constraints\Constraint;
    use Money\Money;
    use stdClass;

    use Enobrev\API\HTTP;
    use Enobrev\ORM\ModifiedDateColumn;
    use Enobrev\ORM\Field;
    use Enobrev\ORM\Table;
    use Enobrev\ORM\Tables;
    use Enobrev\Log;

    use function Enobrev\dbg;
    use function Enobrev\array_from_path;

    use Adbar\Dot;
    use JsonSchema\Validator;
    use Zend\Diactoros\Response as ZendResponse;

    const DEFAULT_SCHEMA = [
        "\$schema" => "http://json-schema.org/draft-07/schema",
        "type"     => "object",
        "additionalProperties" => false,
        "properties" => [
            "request" => [
                "type" => "object",
                "additionalProperties" => false,
                "properties" => [
                    "document" => [
                        "type" => "boolean",
                        "description" => "Output documentation for this endpoint without processing the endpoint method",
                        "default" => false
                    ]
                ]
            ],
            "response" => [
                "type" => "object",
                "properties" => [
                    "_server"    => ["\$ref"=> "#/properties/response/definitions/_server"],
                    "_request"   => ["\$ref"=> "#/properties/response/definitions/_request"],
                    "_response"  => ["\$ref"=> "#/properties/response/definitions/_request"],
                    "__requests" => [
                        "type" => "array",
                        "items" => ["\$ref"=> "#/properties/response/definitions/_request"]
                    ],
                    "jsonschema" => [
                        "type" => "object"
                    ]
                ],
                "definitions" => [
                    "_server" => [
                        "type" => "object",
                        "properties"=> [
                            "timezone"      => ["type" => "string"],
                            "timezone_gmt"  => ["type" => "string"],
                            "date"          => ["type" => "string"],
                            "date_w3c"      => ["type" => "string"]
                        ],
                        "additionalProperties"=> false
                    ],
                    "_request" => [
                        "type" => "object",
                        "properties"=> [
                            "validation" => [
                                "type" => "object",
                                "properties" => [
                                    "status" => [
                                        "type" => "string",
                                        "enum" => ["PASS", "FAIL"]
                                    ],
                                    "errors" => [
                                        "type" => "array",
                                        "items" => ["\$ref" => "#/definitions/response/definitions/_validation_error"]
                                    ]
                                ]
                            ],
                            "logs"      => [
                                "type" => "object",
                                "properties" => [
                                    "thread" => [
                                        "type" => "string",
                                        "description" => "Alphanumeric hash for looking up entire request thread in logs"
                                    ],
                                    "request" => [
                                        "type" => "string",
                                        "description" => "Alphanumeric hash for looking up specific API request in logs"
                                    ]
                                ]
                            ],
                            "method"        => [
                                "type" => "string",
                                "enum" => ["GET", "POST", "PUT", "DELETE"]
                            ],
                            "path"          => ["type" => "string"],
                            "attributes"    => [
                                "type" => "array",
                                "description" => "Parameters pulled from the path"
                            ],
                            "query"         => ["type" => "array"],
                            "data"          => [
                                "oneOf" => [
                                    ["type" => "object"],
                                    ["type" => "null"]
                                ],
                                "description" => "POSTed Data"
                            ]
                        ],
                        "additionalProperties"=> false
                    ],
                    "_response" => [
                        "type" => "object",
                        "properties"=> [
                            "validation" => [
                                "type" => "object",
                                "properties" => [
                                    "status" => [
                                        "type" => "string",
                                        "enum" => ["PASS", "FAIL"]
                                    ],
                                    "errors" => [
                                        "type" => "array",
                                        "items" => ["\$ref" => "#/definitions/response/definitions/_validation_error"]
                                    ]
                                ]
                            ]
                        ],
                        "additionalProperties"=> false
                    ],
                    "_validation_error" => [
                        "type" => "object",
                        "properties" => [
                            "property"      => ["type" => "string"],
                            "pointer"       => ["type" => "string"],
                            "message"       => ["type" => "string"],
                            "constraint"    => ["type" => "string"],
                            "context"       => ["type" => "number"],
                            "minimum"       => ["type" => "number"],
                            "value"         => []
                        ]
                    ]
                ]
            ]
        ],
        "definitions" => []
    ];

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

        /** @var array */
        protected static $aGlobalServer;

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

        /** @var Dot */
        protected $oOutput;

        /** @var  array */
        protected $aHeaders = [];

        /** @var  int */
        protected $iStatus = null;

        /** @var  bool */
        protected $bIncludeRequestInOutput = true;

        /** @var  bool */
        private $bHasResponded = false;

        /** @var Dot */
        private $oSchema = null;

        /** @var array */
        protected static $aAllowedURIs = ['*'];

        /** @var string */
        protected static $sDomain = null;

        /** @var string */
        protected static $sScheme = 'https://';

        /** @var array  */
        public $ValidParams = [];

        /**
         * Response constructor.
         * @param Request $oRequest
         * @throws Exception\Response
         */
        public function __construct(Request $oRequest) {
            if (self::$sDomain === null) {
                throw new Exception\Response('API Response Not Initialized');
            }

            $this->oSchema  = new Dot(DEFAULT_SCHEMA);
            $this->oOutput  = new Dot();
            $this->aHeaders = [];
            $this->includeRequestInOutput(true);
            $this->setRequest($oRequest);
            $this->setFormat($oRequest->Format);
            $this->setStatus(HTTP\OK);

            if (!self::$aGlobalServer) {
                self::$aGlobalServer = [];
            }
        }

        /**
         * @param Request $oRequest
         */
        private function setRequest(Request $oRequest): void {
            $this->Request = $oRequest;
        }

        private function setRequestOutput() {
            $aRequest = [
                'logs' => [
                    'thread'  => Log::getThreadHashForOutput(),
                    'request' => Log::getRequestHashForOutput()
                ],
                'method' => $this->Request->OriginalRequest->getMethod(),
                'path'   => $this->Request->OriginalRequest->getUri()->getPath()
            ];

            if ($this->bIncludeRequestInOutput) {
                $aRequest = array_merge($aRequest, [
                    'attributes' => $this->Request->OriginalRequest->getAttributes(),
                    'query'      => $this->Request->OriginalRequest->getQueryParams(),
                    'data'       => $this->Request->POST
                ]);
            }

            $this->add('_request', $aRequest);
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
                $this->add($sVar->getTitle(), $sVar);
            } else if ($sVar instanceof Field\Date) {
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
                        $this->set($sKey, $sValue);
                    }
                }
            } else if ($mValue instanceof Table) {
                $this->set($sVar, $mValue->getColumnsWithFields());
            } else {
                $this->set($sVar, $mValue);
            }
        }

        /**
         * @param string $sKey
         */
        public function remove(string $sKey): void {
            $this->oOutput->delete($sKey);
        }

        /**
         * This is a workaround to ensure we can set _server values from within multi-requests.  I don't like it, but it gets the job done.
         * @param string $sKey
         * @param mixed  $mValue
         */
        public static function setGlobalServerData(string $sKey, $mValue): void {
            self::$aGlobalServer[$sKey] = $mValue;
        }

        private function setServerOutput(): void {
            if (self::$aGlobalServer && isset(self::$aGlobalServer['date'])) {
                $oNow = self::$aGlobalServer['date'];
            } else {
                $oNow = new \DateTime;
            }

            $aServer = array_merge(self::$aGlobalServer, [
                'timezone'      => $oNow->format('T'),
                'timezone_gmt'  => $oNow->format('P'),
                'date'          => $oNow->format(self::SYNC_DATE_FORMAT),
                'date_w3c'      => $oNow->format(DateTime::W3C)
            ]);

            $this->add('_server', (object) $aServer);
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

            if ($mValue instanceof Field\Date) {
                $mValue = (string) $mValue;
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

            if (is_array($mValue)) {
                $this->oOutput->mergeRecursiveDistinct($sVar, $mValue);
            } else {
                $this->oOutput->set($sVar, $mValue);
            }
        }

        /**
         * Overrides all default output and replaces output object with $oOutput
         * @param array $aOutput
         */
        public function overrideOutput($aOutput): void {
            $this->oOutput = new Dot($aOutput);
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
         * @throws Exception\DocumentationException
         * @throws Exception\NoContentType
         * @throws Exception\InvalidRequest
         */
        public function validateRequest() {
            $bRequestedDocumentation = $this->Request->GET['document'] ?? $this->Request->POST['document'] ?? false;  // TODO: Make me a header

            if ($bRequestedDocumentation) {
                $this->add('jsonschema', (object) $this->oSchema->all());
            }

            $oValidator = new Validator;

            $oTest = (object) [
                'request'  => (object) ($this->Request->isGet() ? $this->Request->GET : $this->Request->POST)
            ];

            $oValidator->validate(
                $oTest,
                json_decode($this->oSchema->toJson()),
                Constraint::CHECK_MODE_APPLY_DEFAULTS
                & Constraint::CHECK_MODE_ONLY_REQUIRED_DEFAULTS
            );

            if (!$oValidator->isValid()) {
                $oDot = new Dot();
                $oDot->set('request', $this->Request->isGet() ? $this->Request->GET : $this->Request->POST);

                $aErrors = [];
                foreach($oValidator->getErrors() as $aError) {
                    $aError['value'] = $oDot->get($aError['property']);
                    $aErrors[]       = $aError;
                }

                $this->add('_request.validation.status', 'FAIL');
                $this->add('_request.validation.errors', $aErrors);

                throw new InvalidRequest();
            } else {
                $this->add('_request.validation.status', 'PASS');
                $this->ValidParams = (object) $oTest->request;
            }

            if ($bRequestedDocumentation) {
                throw new DocumentationException();
            }
        }

        public function validateResponse() {
            $oValidator = new Validator;

            $oTest = (object) [
                'response'  => $this->getOutput()
            ];

            $oValidator->validate($oTest, json_decode($this->oSchema->toJson()));
            if (!$oValidator->isValid()) {
                $oDot = new Dot();
                $oDot->set('response', json_decode(json_encode($this->getOutput()), true));

                $aErrors = [];
                foreach($oValidator->getErrors() as $aError) {
                    $aError['value'] = $oDot->get($aError['property']);
                    $aErrors[]       = $aError;
                }

                $this->add('_response.validation.status', 'FAIL');
                $this->add('_response.validation.errors', $aErrors);
            } else {
                $this->add('_response.validation.status', 'PASS');
            }
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
            $this->setServerOutput();
            $this->setRequestOutput();

            return (object) $this->oOutput->all();
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

        /**
         * @param string $sKey
         * @param array $aDefinition
         */
        public function setSchema(?string $sKey, array $aDefinition):void {
            if (!$sKey) {
                $this->oSchema->mergeRecursiveDistinct($aDefinition);
                return;
            }

            $this->oSchema->mergeRecursiveDistinct($sKey, $aDefinition);
        }
    }