<?php
    namespace Enobrev\API\Spec;

    use cebe\openapi\SpecObjectInterface;
    use Enobrev\API\OpenApiInterface;
    use Exception;

    /** @deprecated ?? */
    class RefResponse implements OpenApiInterface {

        private string $sName;

        public static function create(string $sName):self {
            return new self($sName);
        }

        public function __construct(string $sName) {
            $this->sName = $sName;
        }

        /**
         * @return SpecObjectInterface
         * @throws Exception
         */
        public function getSpecObject(): SpecObjectInterface {
            throw new Exception('I did not think this would ever be called.');
        }
    }