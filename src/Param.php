<?php
    namespace Enobrev\API;

    use cebe\openapi\spec\Parameter;
    use cebe\openapi\spec\Schema;

    abstract class Param {
        protected const STRING     = 'string';
        protected const NUMBER     = 'number';
        protected const INTEGER    = 'integer';
        protected const BOOLEAN    = 'boolean';
        protected const ARRAY      = 'array';
        protected const OBJECT     = 'object';

        public const REQUIRED   = 1;
        public const DEPRECATED = 2;
        public const NULLABLE   = 4;

        /** @var array */
        protected $aValidation;

        /** @var string */
        protected $sType;

        /** @var string */
        protected $sDescription;

        /** @var int */
        protected $iOptions;

        /** @var string */
        protected $sExample;

        /** @var array */
        protected $aExamples;

        public function __construct() {
            $this->aValidation  = [];
            $this->aExamples    = [];
        }

        public function isRequired(): bool {
            return $this->is(self::REQUIRED);
        }

        public function isDeprecated(): bool {
            return $this->is(self::DEPRECATED);
        }

        public function isNullable(): bool {
            return $this->is(self::NULLABLE);
        }

        public function hasDefault():bool {
            return array_key_exists('default', $this->aValidation);
        }

        public function getDefault() {
            return $this->aValidation['default'];
        }

        private function is(int $iOption):bool {
            return $this->iOptions & $iOption;
        }

        protected function getType(): string {
            return $this->sType;
        }

        protected function getValidationForSchema():array {
            $aValidation = $this->aValidation;

            if ($this->isDeprecated()) {
                $aValidation['deprecated'] = true;
            }

            return $aValidation;
        }

        /**
         * @param array $aSchema
         * @return Param\_Array|Param\_Boolean|Param\_Integer|Param\_Number|Param\_Object|Param\_String
         */
        public static function createFromJsonSchema(array $aSchema) {
            $oParam = null;
            switch($aSchema['type']) {
                case 'array':   $oParam = Param\_Array::createFromJsonSchema($aSchema);   break;
                case 'object':  $oParam = Param\_Object::createFromJsonSchema($aSchema);  break;
                case 'string':  $oParam = Param\_String::createFromJsonSchema($aSchema);  break;
                case 'number':  $oParam = Param\_Number::createFromJsonSchema($aSchema);  break;
                case 'integer': $oParam = Param\_Integer::createFromJsonSchema($aSchema); break;
                case 'boolean': $oParam = Param\_Boolean::createFromJsonSchema($aSchema); break;
            }

            if (isset($aSchema['nullable'])) {
                $oParam = $oParam->nullable();
            }

            if (isset($aSchema['deprecated'])) {
                $oParam = $oParam->deprecated();
            }

            if (isset($aSchema['description'])) {
                $oParam->sDescription = $aSchema['description'];
            }

            if (isset($aSchema['properties'])) {
                $aParams = [];
                foreach($aSchema['properties'] as $sParam => $aParam) {
                    $aParams[$sParam] = self::createFromJsonSchema($aParam);
                }
                $oParam = $oParam->items($aParams);
            }

            return $oParam;
        }

        public function getParameter(string $sName, ?string $sIn = 'query'): Parameter {
            $aOptions = [
                'name'   => $sName,
                'schema' => $this->getSchema()
            ];

            if ($sIn) {
                $aOptions['in'] = $sIn;
            }

            if ($this->isRequired()) {
                $aOptions['required'] = true;
            }

            if ($this->isDeprecated()) {
                $aOptions['deprecated'] = true;
            }

            if ($this->sDescription) {
                $aOptions['description'] = $this->sDescription;
            }

            if (!empty($this->sExample)) {
                $aOptions['example'] = $this->sExample;
            }

            if (!empty($this->aExamples)) {
                $aOptions['examples'] = $this->aExamples;
            }

            return new Parameter($aOptions);
        }

        public function getSchema(): Schema {
            $aSchema = $this->aValidation;

            if ($this->isDeprecated()) {
                $aSchema['deprecated'] = true;
            }

            if ($this->isNullable()) {
                $aSchema['nullable'] = true;
            }

            if ($this->sDescription) {
                $aSchema['description'] = $this->sDescription;
            }

            $aSchema['type'] = $this->getType();

            return new Schema($aSchema);
        }
        
        abstract public function coerce($mValue);
    }