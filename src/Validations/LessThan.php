<?php
    namespace Enobrev\API\Validations;

    use Enobrev\API\Param;
    use Enobrev\API\Validation;

    class LessThan implements Validation {
        const REQUIREMENT      = 'This value should be less than {{ iMax }}';

        const CODE_TOO_HIGH    = 'ERROR_TOO_HIGH';

        const MESSAGE_TOO_HIGH = 'This value is too high';

        private $iMax;
        private $aErrors;

        public function __construct(int $iMax) {
            $this->iMax = $iMax;
        }

        public function validate($mValue): bool {
            $this->aErrors = [];

            if ($mValue === Param::VALUE_NOT_SET) {
                return true;
            }

            if ($mValue >= $this->iMax) {
                $this->aErrors[self::CODE_TOO_HIGH] = self::MESSAGE_TOO_HIGH;
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
            $sRequirement = str_replace('{{ iMax }}', $this->iMax, $sRequirement);

            return $sRequirement;
        }

        public function error_codes(): array {
            return [self::CODE_TOO_HIGH];
        }
    }