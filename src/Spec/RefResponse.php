<?php
    namespace Enobrev\API\Spec;

    use cebe\openapi\SpecObjectInterface;
    use Enobrev\API\FullSpec\Component\Reference;
    use Enobrev\API\OpenApiInterface;

    /** @deprecated ?? */
    class RefResponse implements OpenApiInterface {

        /** @var string */
        private $sName;

        public static function create(string $sName):self {
            return new self($sName);
        }

        public function __construct(string $sName) {
            $this->sName = $sName;
        }

        public function getSpecObject(): SpecObjectInterface {
            throw new \Exception('I did not think this would ever be called.');
        }
    }