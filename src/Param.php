<?php
    namespace Enobrev\API;

    abstract class Param implements JsonSchemaInterface {
        const STRING     = 'string';
        const NUMBER     = 'number';
        const INTEGER    = 'integer';
        const BOOLEAN    = 'boolean';
        const ARRAY      = 'array';
        const OBJECT     = 'object';

        const REQUIRED   = 1;
        const DEPRECATED = 2;
        const NULLABLE   = 4;

        /** @var array */
        protected $aValidation;

        /** @var string */
        protected $sName;

        /** @var string */
        protected $sType;

        /** @var string */
        protected $sDescription;

        /** @var int */
        protected $iOptions;

        public function __construct() {
            $this->aValidation  = [];
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

        public function getName():string {
            return $this->sName;
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
                case 'array':   $oParam = Param\_Array::create();   break;
                case 'object':  $oParam = Param\_Object::create();  break;
                case 'string':  $oParam = Param\_String::create();  break;
                case 'number':  $oParam = Param\_Number::create();  break;
                case 'integer': $oParam = Param\_Integer::create(); break;
                case 'boolean': $oParam = Param\_Boolean::create(); break;
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
                    $aParams[$sParam] = Param::createFromJsonSchema($aParam);
                }
                $oParam = $oParam->items($aParams);
            }

            return $oParam;
        }

        public function getJsonSchema($bOpenSchema = false): array {
            $aSchema = $this->getValidationForSchema();
            $aSchema['type'] = $this->getType();

            if ($this->isNullable()) {
                return [
                    'anyOf' => [
                        $aSchema,
                        ['type' => 'null']
                    ]
                ];
            }

            if ($this->sDescription) {
                $aSchema['description'] = $this->sDescription;
            }

            return $aSchema;
        }

        public function getJsonSchemaForOpenAPI(): array {
            $aSchema = $this->getJsonSchema(true);

            if (isset($aSchema['anyOf']) && is_array($aSchema['anyOf'])) {
                foreach($aSchema['anyOf'] as $iIndex => $aAnySchema) {
                    if (isset($aAnySchema['type']) && $aAnySchema['type'] == 'null') {
                        unset($aSchema['anyOf'][$iIndex]);
                        break;
                    }
                }

                if (count($aSchema['anyOf']) === 1) {
                    $aSchema = array_shift($aSchema['anyOf']);
                }
            }

            if ($this->isNullable()) {
                $aSchema['nullable'] = true;
            }

            if ($this->sDescription) {
                $aSchema['description'] = $this->sDescription;
            }

            return $aSchema;
        }

        /**
         * @param string $sName
         * @param null|string $sIn
         * @return array
         */
        public function OpenAPI(string $sName, ?string $sIn = 'query'): array {
            $aOutput = [
                'name'   => $sName,
                'schema' => $this->getJsonSchema()
            ];

            if ($sIn) {
                $aOutput['in'] = $sIn;
            }

            if ($this->isRequired()) {
                $aOutput['required'] = true;
            }

            if ($this->isDeprecated()) {
                $aOutput['deprecated'] = true;
            }

            if ($this->isNullable()) {
                $aOutput['nullable'] = true;
            }

            if ($this->sDescription) {
                $aOutput['description'] = $this->sDescription;
            }

            return $aOutput;
        }

        abstract public function coerce($mValue);
    }