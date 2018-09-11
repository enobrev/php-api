<?php
    namespace Enobrev\API\Param;
    
    use Enobrev\API\Exception;
    use Enobrev\API\Param;
    use Enobrev\API\ParamTrait;
    use function Enobrev\dbg;

    class _Array extends Param {
        use ParamTrait;

        /** @var string */
        protected $sType = Param::ARRAY;

        public function items(Param $oItems): self {
            return $this->validation(['items' => $oItems]);
        }

        public function minItems(int $iMinItems): self {
            return $this->validation(['minItems' => $iMinItems]);
        }

        public function maxItems(int $iMaxItems): self {
            return $this->validation(['maxItems' => $iMaxItems]);
        }

        public function uniqueItems(bool $bUniqueItems = true): self {
            return $this->validation(['uniqueItems' => $bUniqueItems]);
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

        public function getJsonSchemaForOpenAPI(): array {
            return parent::getJsonSchemaForOpenAPI();
        }
    }