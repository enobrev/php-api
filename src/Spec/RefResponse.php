<?php
    namespace Enobrev\API\Spec;

    use Adbar\Dot;
    use Enobrev\API\FullSpec;
    use Enobrev\API\FullSpec\Component\Reference;
    use Enobrev\API\OpenApiInterface;
    use Enobrev\API\OpenApiResponseSchemaInterface;
    use Enobrev\API\Spec;

    class RefResponse implements OpenApiInterface, OpenApiResponseSchemaInterface {

        /** @var string */
        private $sName;

        public static function create(string $sName):self {
            $oResponse = new self($sName);
            return $oResponse;
        }

        public function __construct(string $sName) {
            $this->sName = $sName;
        }

        public function getOpenAPI(): array {
            return Reference::create($this->sName)->getOpenAPI();
        }
    }