<?php
    namespace Enobrev\API;

    trait ParamTrait {
        /**
         * @return static
         */
        public static function create() {
            return new self();
        }

        /**
         * @param bool $bRequired
         *
         * @return static
         */
        public function required(bool $bRequired = true) {
            $oClone = clone $this;
            if ($bRequired) {
                $oClone->iOptions |= self::REQUIRED;
            } else {
                $oClone->iOptions ^= self::REQUIRED;
            }
            return $oClone;
        }

        /**
         * @param bool $bDeprecated
         *
         * @return static
         */
        public function deprecated(bool $bDeprecated = true) {
            $oClone = clone $this;
            if ($bDeprecated) {
                $oClone->iOptions |= self::DEPRECATED;
            } else {
                $oClone->iOptions ^= self::DEPRECATED;
            }
            return $oClone;
        }

        /**
         * @param bool $bNullable
         *
         * @return static
         */
        public function nullable(bool $bNullable = true) {
            $oClone = clone $this;
            if ($bNullable) {
                $oClone->iOptions |= self::NULLABLE;
            } else {
                $oClone->iOptions ^= self::NULLABLE;
            }
            return $oClone;
        }

        /**
         * @param string $sType
         *
         * @return static
         */
        public function type(string $sType) {
            $oClone = clone $this;
            $oClone->sType = $sType;
            return $oClone;
        }

        /**
         * @param array $aValidation
         *
         * @return static
         */
        public function validation(array $aValidation) {
            $oClone = clone $this;
            $oClone->aValidation = array_merge($oClone->aValidation, $aValidation);
            return $oClone;
        }

        /**
         * @param array $aEnum
         *
         * @return static
         */
        public function enum(array $aEnum) {
            return $this->validation(['enum' => $aEnum]);
        }

        /**
         * @param $mDefault
         *
         * @return static
         */
        public function default($mDefault) {
            if ($mDefault === null) {
                return $this->nullable()->validation(['default' => $mDefault]);
            }

            return $this->validation(['default' => $mDefault]);
        }

        /**
         * @param $mExample
         *
         * @return static
         */
        public function example($mExample) {
            if (!is_string($mExample)) {
                $mExample = json_encode($mExample);
            }

            $oClone = clone $this;
            $oClone->sExample = $mExample;
            return $oClone;
        }

        /**
         * @param string      $sName
         * @param mixed       $mValue
         * @param string|null $sSummary
         * @param string|null $sDescription
         *
         * @return static
         */
        public function addExample(string $sName, $mValue, ?string $sSummary = null, ?string $sDescription = null) {
            $aExamples = $this->aExamples;
            $aExamples[$sName] = [
                'value' => $mValue
            ];

            if ($sSummary) {
                $aExamples[$sName]['summary'] = $sSummary;
            }

            if ($sDescription) {
                $aExamples[$sName]['description'] = $sDescription;
            }

            $oClone = clone $this;
            $oClone->aExamples = $aExamples;
            return $oClone;
        }

        /**
         * @param string $sDescription
         *
         * @return static
         */
        public function description(string $sDescription) {
            $oClone = clone $this;
            $oClone->sDescription = $sDescription;
            return $oClone;
        }
    }