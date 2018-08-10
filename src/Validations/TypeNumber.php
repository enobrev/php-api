<?php
    namespace Enobrev\API\Validations;

    use Enobrev\API\Param;
    use Enobrev\API\Validation;

    class TypeNumber implements Validation {
        const REQUIREMENT     = 'This value should be a number';

        const CODE_NOT_NUMBER    = 'ERROR_NOT_NUMBER';

        const MESSAGE_NOT_NUMBER = 'This value is not a number';

        private $aErrors;

        public function validate($mValue): bool {
            $this->aErrors = [];

            if ($mValue === Param::VALUE_NOT_SET) {
                return true;
            }

            if (!is_numeric($mValue)) {
                $this->aErrors[self::CODE_NOT_NUMBER] = self::MESSAGE_NOT_NUMBER;
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
            return [self::CODE_NOT_NUMBER];
        }
    }