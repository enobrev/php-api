<?php
    namespace Enobrev\API\Param;
    
    use Enobrev\API\Exception;
    use Enobrev\API\Param;
    use function Enobrev\dbg;

    class _Array extends Param {
        public static function create(): self {
            return new self();
        }

        public function __construct() {
            parent::__construct(Param::ARRAY);
        }

        public function items(Param $oItems): self {
            $this->validation(['items' => $oItems]);
            return $this;
        }

        protected function getValidationForSchema():array {
            $aValidation = parent::getValidationForSchema();
            if ($aValidation['items'] instanceof Param) {
                $aValidation['items'] = $aValidation['items']->getJsonSchema();
            }

            return $aValidation;
        }

        public function getJsonSchema(): array {
            if (!isset($this->aValidation['items'])) {
                throw new Exception('Array Param requires items definition');
            }

            return parent::getJsonSchema();
        }

        public function getOpenAPI(): array {
            return parent::getOpenAPI();
        }
    }