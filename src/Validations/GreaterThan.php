<?php
    namespace Enobrev\API\Validations;

    use Enobrev\API\Param;
    use Enobrev\API\Validation;

    class GreaterThan implements Validation {
        const REQUIREMENT     = 'This value should be greater than {{ iMin }}';

        const CODE_TOO_LOW    = 'ERROR_TOO_LOW';

        const MESSAGE_TOO_LOW = 'This value is too low';

        private $iMin;
        private $aErrors;

        public function __construct(int $iMin) {
            $this->iMin = $iMin;
        }

        public function validate($mValue): bool {
            $this->aErrors = [];

            if ($mValue === Param::VALUE_NOT_SET) {
                return true;
            }

            if ($mValue <= $this->iMin) {
                $this->aErrors[self::CODE_TOO_LOW] = self::MESSAGE_TOO_LOW;
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
            $sRequirement = str_replace('{{ iMin }}', $this->iMin, $sRequirement);

            return $sRequirement;
        }

        public function error_codes(): array {
            return [self::CODE_TOO_LOW];
        }
    }