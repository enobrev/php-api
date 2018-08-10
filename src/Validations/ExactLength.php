<?php
    namespace Enobrev\API\Validations;

    use Enobrev\API\Param;
    use Enobrev\API\Validation;

    class ExactLength implements Validation {
        const REQUIREMENT       = 'This value should have {{ iLength }} characters';

        const CODE_TOO_SHORT    = 'ERROR_TOO_SHORT';
        const CODE_TOO_LONG     = 'ERROR_TOO_LONG';

        const MESSAGE_TOO_SHORT = 'This value is too short';
        const MESSAGE_TOO_LONG  = 'This value is too long';

        private $iLength;
        private $aErrors;

        public function __construct(int $iLength) {
            $this->iLength = $iLength;
        }

        public function validate($mValue): bool {
            $this->aErrors = [];

            if ($mValue === Param::VALUE_NOT_SET) {
                return true;
            }

            if (strlen($mValue) < $this->iLength) {
                $this->aErrors[self::CODE_TOO_SHORT] = self::MESSAGE_TOO_SHORT;
            }

            if (strlen($mValue) > $this->iLength) {
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
            $sRequirement = str_replace('{{ iLength }}', $this->iLength, $sRequirement);

            return $sRequirement;
        }

        public function error_codes(): array {
            return [self::CODE_TOO_SHORT, self::CODE_TOO_LONG];
        }
    }