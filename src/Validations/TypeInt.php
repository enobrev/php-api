<?php
    namespace Enobrev\API\Validations;

    use Enobrev\API\Param;
    use Enobrev\API\Validation;

    class TypeInt implements Validation {
        const REQUIREMENT     = 'This value should be a int';

        const CODE_NOT_INT    = 'ERROR_NOT_INT';

        const MESSAGE_NOT_INT = 'This value is not a int';

        private $aErrors;

        public function validate($mValue): bool {
            $this->aErrors = [];

            if ($mValue === Param::VALUE_NOT_SET) {
                return true;
            }

            if (!ctype_digit($mValue) && !is_int($mValue)) {
                $this->aErrors[self::CODE_NOT_INT] = self::MESSAGE_NOT_INT;
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
            return [self::CODE_NOT_INT];
        }
    }