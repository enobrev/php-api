<?php
    namespace Enobrev\API\Validations;

    use Enobrev\API\Param;
    use Enobrev\API\Validation;

    class MinLength implements Validation {
        const REQUIREMENT       = 'This value should have at least {{ iMinLength }} characters';

        const CODE_TOO_SHORT    = 'ERROR_TOO_SHORT';

        const MESSAGE_TOO_SHORT = 'This value is too short';

        private $iMinLength;
        private $aErrors;

        public function __construct(int $iMinLength) {
            $this->iMinLength = $iMinLength;
        }

        public function validate($mValue): bool {
            $this->aErrors = [];

            if ($mValue === Param::VALUE_NOT_SET) {
                return true;
            }

            if ($this->iMinLength !== null && strlen($mValue) < $this->iMinLength) {
                $this->aErrors[self::CODE_TOO_SHORT] = self::MESSAGE_TOO_SHORT;
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
            $sRequirement = str_replace('{{ iMinLength }}', $this->iMinLength, $sRequirement);

            return $sRequirement;
        }

        public function error_codes(): array {
            return [self::CODE_TOO_SHORT];
        }
    }