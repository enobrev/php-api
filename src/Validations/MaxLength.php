<?php
    namespace Enobrev\API\Validations;

    use Enobrev\API\Param;
    use Enobrev\API\Validation;

    class MaxLength implements Validation {
        const REQUIREMENT       = 'This value should have at least {{ iMaxLength }} characters';

        const CODE_TOO_LONG     = 'ERROR_TOO_LONG';

        const MESSAGE_TOO_LONG  = 'This value is too short';

        private $iMaxLength;
        private $aErrors;

        public function __construct(int $iMaxLength) {
            $this->iMaxLength = $iMaxLength;
        }

        public function validate($mValue): bool {
            $this->aErrors = [];

            if ($mValue === Param::VALUE_NOT_SET) {
                return true;
            }

            if ($this->iMaxLength !== null && strlen($mValue) > $this->iMaxLength) {
                $this->aErrors[self::CODE_TOO_LONG] = self::MESSAGE_TOO_LONG;
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
            $sRequirement = self::REQUIREMENT;
            $sRequirement = str_replace('{{ iMaxLength }}', $this->iMaxLength, $sRequirement);

            return $sRequirement;
        }

        public function error_codes(): array {
            return [self::CODE_TOO_LONG];
        }
    }