<?php
    namespace Enobrev\API;

    use Enobrev\API\Exception;

    abstract class Base {
        /** @var  Request */
        public $Request = null;

        /** @var  Response */
        public $Response = null;

        /** @var string */
        private static $sBaseURI = null;

        /**
         * @param string $sBaseURI
         */
        public static function setBaseURI(string $sBaseURI): void {
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

        public function setRequest(Request $oRequest): void {
            $this->Request = $oRequest;
        }

        protected function initFromRoute(): void {

        }

        /**
         * @param array[] ...$aSegments
         * @return string
         */
        protected static function joinPath(...$aSegments) {
            /**
             * @param mixed $sSegment
             * @return string
             */
            $fStringify = function ($sSegment): string {
                return (string) $sSegment;
            };

            return '/' . implode('/', array_map($fStringify, $aSegments));
        }

        /**
         * @return array
         */
        protected function getMethodArray() {
            return array_map('strtoupper', get_class_methods($this));
        }

        public function options(): void {
            $this->Response->setAllow(
                array_intersect(
                    $this->getMethodArray(),
                    Method\_ALL
                )
            );

            $this->Response->statusNoContent();
        }

        public function methodNotAllowed(): void {
            $this->Response->statusMethodNotAllowed();
        }

        public function spec(FullSpec &$oFullSpec) {
        }
    }