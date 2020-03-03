<?php
    namespace Enobrev\API\Param;
    
    use Enobrev\API\Param;
    use Enobrev\API\ParamTrait;

    class _Boolean extends Param {
        use ParamTrait;

        /** @var string */
        protected $sType = Param::BOOLEAN;

        public function default($bDefault):self {
            return $this->validation(['default' => (bool) $bDefault]);
        }

        /**
         * Heavily inspired by justinrainbow/json-schema, except tries not to coerce nulls into non-nulls
         * @param $mValue
         * @return bool|null
         */
        public function coerce($mValue) {
            if ($this->isNullable()) {
                if ($mValue === null || $mValue === 'null' || $mValue === '') {
                    return null;
                }
            }

            if ($mValue === 1 || $mValue === 'true') {
                return true;
            }

            if ($mValue === null || $mValue === 0 || $mValue === 'false') {
                return false;
            }

            if (is_array($mValue) && count($mValue) === 1) {
                return $this->coerce(reset($mValue));
            }

            return $mValue;
        }
        
        /**
         * @param array $aSchema
         * @return Param\_String
         */
        public static function createFromJsonSchema(array $aSchema) {
            $oParam = self::create();

            if (isset($aSchema['default'])) {
                $oParam = $oParam->default($aSchema['default']);
            }

            return $oParam;
        }
    }