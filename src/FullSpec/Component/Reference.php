<?php
    namespace Enobrev\API\FullSpec\Component;

    use Enobrev\API\FullSpec\ComponentInterface;
    use Enobrev\API\OpenApiInterface;

    class Reference implements ComponentInterface, OpenApiInterface {

        /** @var string */
        private $sName;

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

        public function getOpenAPI(): array {
            return ['$ref' => "#/components/{$this->sName}"];
        }
    }