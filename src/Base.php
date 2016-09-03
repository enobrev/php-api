<?php
    namespace Enobrev\API;

    use Enobrev\API\Exception;

    abstract class Base {
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

        public function options() {
            $this->Response->setAllow(
                array_intersect(
                    $this->getMethodArray(),
                    Method\_ALL
                )
            );

            $this->Response->statusNoContent();
        }

        public function methodNotAllowed() {
            $this->Response->statusMethodNotAllowed();
        }
    }