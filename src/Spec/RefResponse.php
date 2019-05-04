<?php
    namespace Enobrev\API\Spec;

    use Enobrev\API\FullSpec\Component\Reference;
    use Enobrev\API\OpenApiInterface;
    use Enobrev\API\OpenApiResponseSchemaInterface;

    class RefResponse implements OpenApiInterface, OpenApiResponseSchemaInterface {

        /** @var string */
        private $sName;

        public static function create(string $sName):self {
            return new self($sName);
        }

        public function __construct(string $sName) {
            $this->sName = $sName;
        }

        public function getOpenAPI(): array {
            return Reference::create($this->sName)->getOpenAPI();
        }
    }