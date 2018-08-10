<?php
    namespace Enobrev\API\Validations;

    use Enobrev\API\Param;
    use Enobrev\API\Validation;

    class TypeString implements Validation {
        const REQUIREMENT        = 'This value should be a string';

        const CODE_NOT_STRING    = 'ERROR_NOT_STRING';

        const MESSAGE_NOT_STRING = 'This value is not a string';

        private $aErrors;

        public function validate($mValue): bool {
            $this->aErrors = [];

            if ($mValue === Param::VALUE_NOT_SET) {
                return true;
            }

            if (!is_string($mValue)) {
                $this->aErrors[self::CODE_NOT_STRING] = self::MESSAGE_NOT_STRING;
            }

            if (count($this->aErrors)) {
                return false;
            }

            return true;
        }

        public function errors(): ?array {
            return $this->aErrors;
        }

        public function requirement() :string {
            return self::REQUIREMENT;
        }

        public function error_codes(): array {
            return [self::CODE_NOT_STRING];
        }
    }