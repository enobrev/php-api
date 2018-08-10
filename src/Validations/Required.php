<?php
    namespace Enobrev\API\Validations;

    use Enobrev\API\Param;
    use Enobrev\API\Validation;
    use function Enobrev\dbg;

    class Required implements Validation {
        const REQUIREMENT       = 'This value is required';

        const CODE_REQUIRED     = 'ERROR_REQUIRED';

        const MESSAGE_REQUIRED  = 'This value is required and was not set';

        private $aErrors;

        public function validate($mValue): bool {
            $this->aErrors = [];

            if ($mValue === Param::VALUE_NOT_SET) {
                $this->aErrors[self::CODE_REQUIRED] = self::MESSAGE_REQUIRED;
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

        public function error_codes() :array {
            return [self::CODE_REQUIRED];
        }
    }