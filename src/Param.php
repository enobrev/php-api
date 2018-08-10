<?php
    namespace Enobrev\API;


    use function Enobrev\dbg;

    class Param {
        const STRING = 'STRING';
        const INT    = 'INT';
        const NUMBER = 'NUMBER';

        const TYPES = [self::STRING, self::INT, self::NUMBER];

        /** @var string */
        private $sType = null;

        /** @var string */
        private $sName = null;

        /** @var mixed */
        private $mValue = self::VALUE_NOT_SET;

        /** @var Validation[] */
        private $aValidations;

        const VALUE_NOT_SET = '_VALUE_NOT_SET_';

        const REQUIRED = 1;

        public function __construct(string $sName, string $sType, array $aValidations = []) {
            $this->validations($aValidations);
            $this->name($sName);
            $this->type($sType);
        }

        public function name(?string $sName = null) :?string  {
            if ($sName) {
                $this->sName = $sName;
            }

            return $this->sName;
        }

        public function type(?string $sType = null) :?string {
            if ($sType) {
                $this->sType = $sType;

                switch($this->sType) {
                    case self::STRING: array_unshift($this->aValidations, new Validations\TypeString); break;
                    case self::INT:    array_unshift($this->aValidations, new Validations\TypeInt);    break;
                    case self::NUMBER: array_unshift($this->aValidations, new Validations\TypeNumber); break;
                }
            }

            return $this->sType;
        }

        /**
         * @param array|null $aValidations
         * @return Validation[]|null
         */
        public function validations(?array $aValidations = null) :?array  {
            if ($aValidations) {
                $this->aValidations = $aValidations;
            }

            return $this->aValidations;
        }

        public function value($mValue = null) {
            if ($mValue) {
                $this->mValue = $mValue;
            }

            return $this->mValue;
        }

        public function document(): ?array {
            $aValidations = [];
            $aErrorCodes  = [];

            foreach($this->validations() as $oValidation) {
                $aValidations[] = $oValidation->requirement();
                $aErrorCodes    = array_merge($aErrorCodes, $oValidation->error_codes());
            }

            return ([
                'name'        => $this->name(),
                'type'        => $this->type(),
                'validations' => $aValidations,
                'error_codes' => $aErrorCodes,
            ]);
        }

        public function validate() : ?array {
            $aErrors = [];
            foreach($this->aValidations as $oValidation) {
                if (!$oValidation->validate($this->value())) {
                    $aErrors += $oValidation->errors();
                }
            }

            if (count($aErrors)) {
                return $aErrors;
            }

            return null;
        }
    }