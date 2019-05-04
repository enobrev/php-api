<?php
    namespace Enobrev\API\Param;
    
    use Enobrev\API\Exception;
    use Enobrev\API\Param;
    use Enobrev\API\ParamTrait;

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

        /**
         * @param bool $bOpenSchema
         *
         * @return array
         * @throws Exception
         */
        public function getJsonSchema($bOpenSchema = false): array {
            if (!isset($this->aValidation['items'])) {
                throw new Exception('Array Param requires items definition');
            }

            return parent::getJsonSchema($bOpenSchema);
        }

        /**
         * Heavily inspired by justinrainbow/json-schema, except tries not to coerce nulls into non-nulls
         * @param $mValue
         * @return array
         */
        public function coerce($mValue) {
            if ($this->isNullable()) {
                if ($mValue === null || $mValue === 'null' || $mValue === 0 || $mValue === false || $mValue === '') {
                    return null;
                }
            }

            if (is_scalar($mValue) && strpos($mValue, ',') !== false) {
                $mValue = explode(',', $mValue);
                $mValue = array_map('trim', $mValue);
            }

            if (is_scalar($mValue) || $mValue === null) {
                $mValue = [$mValue];
            }

            if (is_array($mValue) && isset($this->aValidation['items'])) {
                $oItems = $this->aValidation['items'];
                if ($oItems instanceof Param) {
                    foreach ($mValue as &$mItem) {
                        $mItem = $oItems->coerce($mItem);
                    }
                }
            }

            return $mValue;
        }
    }