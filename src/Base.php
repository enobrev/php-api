<?php
    namespace Enobrev\API;

    use Enobrev\API\Exception;
    use Enobrev\Log;

    abstract class Base {
        protected $DefaultRole = Role\FORBIDDEN;

        /** @var  Request */
        public $Request;

        /** @var  Response */
        public $Response;

        /** @var string */
        private static $sBaseURI = null;

        /**
         * @param string $sBaseURI
         */
        public static function setBaseURI(string $sBaseURI) {
            self::$sBaseURI = $sBaseURI;
        }

        /**
         * @param string $sClass
         * @param string $sMethod
         * @return string
         */
        protected static function getPath($sClass, $sMethod = null) {
            $aNS    = explode('\\',__NAMESPACE__);
            $sClass = strtolower(str_replace(__NAMESPACE__ . '\\', '', $sClass));
            return implode('/', [end($aNS), $sClass, $sMethod]);
        }

        /**
         * @param string $sClass
         * @param string $sMethod
         * @return string
         * @throws Exception\MissingBaseURI
         */
        protected static function getUri($sClass, $sMethod) {
            if (self::$sBaseURI === null) {
                throw new Exception\MissingBaseURI();
            }

            return self::$sBaseURI . self::getPath($sClass, $sMethod);
        }

        /**
         * Base constructor.
         * @param Request $oRequest
         */
        public function __construct(Request $oRequest) {
            $this->Response = new Response($oRequest);
            $this->setRequest($oRequest);
            $this->initFromRoute();
        }

        public function setRequest(Request $oRequest) {
            $this->Request = $oRequest;
        }

        protected function initFromRoute() {

        }

        /**
         * @param array ...$aSegments
         * @return string
         */
        protected static function joinPath(...$aSegments) {
            return '/' . implode('/',
                array_map(function ($sSegment) {
                    return (string) $sSegment;
                }, $aSegments)
            );
        }

        /**
         * @return array
         */
        protected function getMethodArray() {
            return array_map('strtoupper', get_class_methods($this));
        }

        protected function isAdmin() {
            return false;
        }

        /**
         * @return string
         */
        protected function role() {
            if ($this->isAdmin()) {
                return Role\ADMIN;
            }

            return Role\FORBIDDEN;
        }

        protected function ensureRoles(...$aAcceptedRoles) {
            $this->checkAuthentication();
            return in_array($this->role(), $aAcceptedRoles);
        }

        protected function ensureNotRoles(...$aUnacceptableRoles) {
            $this->checkAuthentication();
            return !in_array($this->role(), $aUnacceptableRoles);
        }

        public function options() {
            $this->Response->setAllow(
                array_intersect(
                    $this->getMethodArray(),
                    Method\_ALL
                )
            );

            $this->Response->statusNoContent();
        }

        protected function checkAuthentication() {
            $aBearer = $this->Request->OriginalRequest->getHeader('Authorization');
            Log::d('Base.checkAuthentication', ['bearer' => $aBearer]);

            return count($aBearer) > 0 ? $aBearer[0] : null;
        }

        protected function requireAuthenticationHeadersOnly(): bool {
            $this->checkAuthentication();

            return false;
        }

        protected function requireAdministrativeAccess(): bool {
            if (!$this->requireAuthentication()) {
                $this->Response->statusUnauthorized();
                return false;
            }

            return true;
        }

        protected function requireAuthentication(): bool {
            $this->checkAuthentication();
            return false;
        }

        public function methodNotAllowed() {
            $this->Response->statusMethodNotAllowed();
        }
    }