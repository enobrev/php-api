<?php
    namespace Enobrev\API;


    class Param {
        const STRING     = 1;
        const NUMBER     = 2;
        const INTEGER    = 4;
        const BOOLEAN    = 8;
        const ARRAY      = 16;
        const OBJECT     = 32;

        const REQUIRED   = 128;
        const DEPRECATED = 256;
        const REFERENCE  = 512;

        const ANYOF      = 1024;


        /** @var string */
        public $sName;

        /** @var string */
        public $sDescription;

        /** @var array */
        public $aValidation;

        /** @var int */
        public $iOptions;

        public function __construct(string $sName, int $iOptions = 0, ?array $aValidation = null, ?string $sDescription = null) {
            $this->sName        = $sName;
            $this->iOptions     = $iOptions;
            $this->aValidation  = $aValidation;
            $this->sDescription = $sDescription;
        }

        /**
         * @param int $iOption
         * @return int
         */
        public function is(int $iOption) {
            return $this->iOptions & $iOption;
        }

        public function type(): string {
            switch(true) {
                case $this->is(self::NUMBER):  return 'number';
                case $this->is(self::INTEGER): return 'integer';
                case $this->is(self::BOOLEAN): return 'boolean';
                case $this->is(self::ARRAY):   return 'array';
                case $this->is(self::OBJECT):  return 'object';
                default:
                case $this->is(self::STRING):  return 'string';
            }
        }

        public function required(): bool {
            return $this->is(self::REQUIRED);
        }

        public function JsonSchema(): array {
            if ($this->is(self::REFERENCE)) {
                return $this->aValidation;
            } else if ($this->is(self::ANYOF)) {
                return [
                    'anyOf' => [
                        $this->aValidation
                    ]
                ];
            }

            $aSchema = $this->aValidation;
            $aSchema['type'] = $this->type();
            return $aSchema;
        }

        /**
         * @param string $sIn
         * @return array
         */
        public function OpenAPI($sIn = 'query'): array {
            $aSchema = $this->aValidation;
            $aSchema['type'] = $this->type();

            $aOutput = [
                'in' => $sIn,
                'name' => $this->sName,
                'schema' => $this->JsonSchema()
            ];

            if ($this->required()) {
                $aOutput['required'] = true;
            }

            if ($this->sDescription) {
                $aOutput['description'] = $this->sDescription;
            }

            return $aOutput;
        }
    }