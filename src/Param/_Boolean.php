<?php
    namespace Enobrev\API\Param;
    
    use cebe\openapi\spec\Schema;
    use Enobrev\API\Param;
    use Enobrev\API\ParamTrait;

    class _Boolean extends Param {
        use ParamTrait;

        /** @var string */
        protected $sType = Param::BOOLEAN;

        public function default($mDefault):self {
            return $this->validation(['default' => (bool) $mDefault]);
        }

        /**
         * Heavily inspired by justinrainbow/json-schema, except tries not to coerce nulls into non-nulls
         * @param $mValue
         * @return bool|null
         */
        public function coerce($mValue) {
            if ($this->isNullable()) {
                if ($mValue === null || strtolower($mValue) === 'null' || $mValue === '') {
                    return null;
                }
            }

            if ($mValue === 1 || $mValue === '1' || strtolower($mValue) === 'true') {
                return true;
            }

            if ($mValue === null || $mValue === 0 || $mValue === '0' || strtolower($mValue) === 'false') {
                return false;
            }

            if (is_array($mValue) && count($mValue) === 1) {
                return $this->coerce(reset($mValue));
            }

            return $mValue;
        }
        
        /**
         * @param Schema $oSchema
         * @return self
         */
        public static function createFromSchema(Schema $oSchema): self {
            $oParam = self::create();

            if ($oSchema->default) {
                $oParam = $oParam->default($oSchema->default);
            }

            return $oParam;
        }
    }