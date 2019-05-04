<?php
    namespace Enobrev\API\FullSpec\Component;

    use Enobrev\API\FullSpec\ComponentInterface;
    use Enobrev\API\OpenApiInterface;
    use Enobrev\API\Param;

    class ParamSchema implements ComponentInterface, OpenApiInterface {
        const PREFIX = 'schemas';

        /** @var string */
        private $sName;

        /** @var Param\_Object */
        private $oParam;

        public static function create(string $sName) {
            return new self($sName);
        }

        public function __construct($sName) {
            $aName = explode('/', $sName);
            if (count($aName) === 1) {
                array_unshift($aName, self::PREFIX);
            } else if ($aName[0] !== self::PREFIX) {
                array_unshift($aName, self::PREFIX);
            };

            $this->sName = implode('/', $aName);
        }

        public function getName(): string {
            return $this->sName;
        }

        public function param(Param\_Object $oParam):self {
            $this->oParam = $oParam;
            return $this;
        }

        public function getParam():Param\_Object {
            return $this->oParam;
        }

        public function getOpenAPI(): array {
            return $this->oParam->getJsonSchemaForOpenAPI();
        }
    }