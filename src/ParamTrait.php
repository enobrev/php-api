<?php
    namespace Enobrev\API;


    trait ParamTrait {
        public static function create(): self {
            return new self();
        }

        public function required(bool $bRequired = true):self {
            $oClone = clone $this;
            if ($bRequired) {
                $oClone->iOptions |= self::REQUIRED;
            } else {
                $oClone->iOptions ^= self::REQUIRED;
            }
            return $oClone;
        }

        public function deprecated(bool $bDeprecated = true):self {
            $oClone = clone $this;
            if ($bDeprecated) {
                $oClone->iOptions |= self::DEPRECATED;
            } else {
                $oClone->iOptions ^= self::DEPRECATED;
            }
            return $oClone;
        }

        public function nullable(bool $bNullable = true):self {
            $oClone = clone $this;
            if ($bNullable) {
                $oClone->iOptions |= self::NULLABLE;
            } else {
                $oClone->iOptions ^= self::NULLABLE;
            }
            return $oClone;
        }

        public function type(string $sType):self {
            $oClone = clone $this;
            $oClone->sType = $sType;
            return $oClone;
        }

        public function validation(array $aValidation):self {
            $oClone = clone $this;
            $oClone->aValidation = array_merge($oClone->aValidation, $aValidation);
            return $oClone;
        }

        public function enum(array $aEnum): self {
            return $this->validation(['enum' => $aEnum]);
        }

        public function default($mDefault): self {
            if ($mDefault === null) {
                return $this->nullable()->validation(['default' => $mDefault]);
            }

            return $this->validation(['default' => $mDefault]);
        }

        public function example($mDefault): self {
            return $this->validation(['example' => $mDefault]);
        }

        public function description(string $sDescription):self {
            $oClone = clone $this;
            $oClone->sDescription = $sDescription;
            return $oClone;
        }
    }