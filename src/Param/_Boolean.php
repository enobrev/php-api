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

        public function getJsonSchema($bOpenSchema = false): array {
            return parent::getJsonSchema($bOpenSchema);
        }

        public function getJsonSchemaForOpenAPI(): array {
            return parent::getJsonSchemaForOpenAPI();
        }

        /**
         * Heavily inspired by justinrainbow/json-schema, except tries not to coerce nulls into non-nulls
         * @param $mValue
         * @return string
         */
        public function coerce($mValue) {
            if ($this->isNullable()) {
                if (is_null($mValue) || $mValue == 'null' || $mValue === '') {
                    return null;
                }
            }

            if ($mValue === 1 || $mValue === 'true') {
                return true;
            }

            if (is_null($mValue) || $mValue === 0 || $mValue === 'false') {
                return false;
            }

            if (is_array($mValue) && count($mValue) === 1) {
                return $this->coerce(reset($mValue));
            }

            return $mValue;
        }
    }