<?php
    namespace Enobrev\API\Param;
    
    use Enobrev\API\Param;
    use Enobrev\API\ParamTrait;

    class _Number extends Param {
        use ParamTrait;

        /** @var string */
        protected $sType = Param::NUMBER;

        public function minimum(int $iMinimum, $bExclusive = false): self {
            if ($bExclusive) {
                return $this->validation(['minimum' => $iMinimum])->validation(['exclusiveMinimum' => $bExclusive]);
            }
            return $this->validation(['minimum' => $iMinimum]);
        }

        public function maximum(int $iMaximum, $bExclusive = false): self {
            if ($bExclusive) {
                return $this->validation(['maximum' => $iMaximum])->validation(['exclusiveMaximum' => $bExclusive]);
            }
            return $this->validation(['maximum' => $iMaximum]);
        }

        public function getJsonSchema($bOpenSchema = false): array {
            return parent::getJsonSchema($bOpenSchema);
        }

        public function getJsonSchemaForOpenAPI(): array {
            return parent::getJsonSchemaForOpenAPI();
        }

        /**
         * Heavily inspired by justinrainbow/json-schema, except tries not to coerce nulls into non-nulls
         * @param $mValue
         * @return float|int
         */
        public function coerce($mValue) {
            if ($this->isNullable()) {
                if (is_null($mValue) || $mValue == 'null' || $mValue === false || $mValue === '') {
                    return null;
                }
            }

            if (is_numeric($mValue)) {
                return $mValue + 0; // cast to number
            }

            /*
            if (is_bool($mValue) || is_null($mValue)) {
                return (int) $mValue;
            }

            if (is_array($mValue) && count($mValue) === 1) {
                return $this->coerce(reset($mValue));
            }
            */

            return $mValue;
        }
    }