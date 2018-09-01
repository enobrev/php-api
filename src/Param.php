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
            $aSchema = $this->aValidation;
            if ($this->is(self::ARRAY) && $aSchema['items'] instanceof self) {
                $aSchema['items'] = $aSchema['items']->JsonSchema(null);
            }

            if ($this->is(self::REFERENCE)) {
                return $aSchema;
            } else if ($this->is(self::ANYOF)) {
                return [
                    'anyOf' => [
                        $aSchema
                    ]
                ];
            }

            $aSchema['type'] = $this->type();
            return $aSchema;
        }

        /**
         * @param string|null $sIn
         * @return array
         */
        public function OpenAPI(?string $sIn = 'query'): array {
            $aSchema = $this->aValidation;
            if ($this->is(self::ARRAY) && $aSchema['items'] instanceof self) {
                $aSchema['items'] = $aSchema['items']->OpenAPI(null);
            }

            $aSchema['type'] = $this->type();

            $aOutput = [
                'name'   => $this->sName,
                'schema' => $this->JsonSchema()
            ];

            if ($sIn) {
                $aOutput['in'] = $sIn;
            }

            if ($this->required()) {
                $aOutput['required'] = true;
            }

            if ($this->sDescription) {
                $aOutput['description'] = $this->sDescription;
            }

            return $aOutput;
        }
    }