<?php
    namespace Enobrev\API\Param;
    
    use cebe\openapi\spec\Schema;
    use Enobrev\API\Param;
    use Enobrev\API\ParamTrait;

    class _Number extends Param {
        use ParamTrait;

        /** @var string */
        protected $sType = Param::NUMBER;

        /**
         *
            "float":    Floating-point numbers.
            "double": 	Floating-point numbers with double precision.
         * @param string $sFormat
         * @return _Number
         */
        public function format(string $sFormat): self {
            return $this->validation(['format' => $sFormat]);
        }

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

        /**
         * Heavily inspired by justinrainbow/json-schema, except tries not to coerce nulls into non-nulls
         * @param $mValue
         * @return float|int
         */
        public function coerce($mValue) {
            if ($this->isNullable()) {
                if ($mValue === null || $mValue === 'null' || $mValue === false || $mValue === '') {
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
        
        /**
         * @param Schema $oSchema
         * @return self
         */
        public static function createFromSchema(Schema $oSchema): self {
            $oParam = self::create();

            if ($oSchema->minimum) {
                $oParam = $oParam->minimum($oSchema->minimum);
            }

            if ($oSchema->maximum) {
                $oParam = $oParam->maximum($oSchema->maximum);
            }

            if ($oSchema->format) {
                $oParam = $oParam->format($oSchema->format);
            }

            return $oParam;
        }
    }