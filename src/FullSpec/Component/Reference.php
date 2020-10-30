<?php
    namespace Enobrev\API\FullSpec\Component;

    use cebe\openapi\spec\Reference as OpenApi_Reference;
    use cebe\openapi\SpecObjectInterface;

    use Enobrev\API\FullSpec\ComponentInterface;
    use Enobrev\API\OpenApiInterface;

    class Reference implements ComponentInterface, OpenApiInterface {

        private string $sName;

        public static function create(string $sName) {
            return new self($sName);
        }

        public function __construct($sName) {
            $this->sName = $sName;
        }

        public function name(string $sName):self {
            $this->sName = $sName;
            return $this;
        }
        
        public function getName(): string {
            return $this->sName;
        }

        public function getPath() {
            return "#/components/{$this->sName}";
        }

        /**
         * @return SpecObjectInterface
         */
        public function getSpecObject(): SpecObjectInterface {
            return new OpenApi_Reference(['$ref' => $this->getPath()]);
        }
    }