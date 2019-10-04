<?php
    namespace Enobrev\API\Spec;

    use Middlewares\Utils\HttpErrorException;

    trait ErrorResponseTrait {

        public static function create():self {
            return new self();
        }

        public static function createFromException(HttpErrorException $oException):self {
            $oResponse = new self();
            $oResponse->message($oException->getMessage());
            $oResponse->code($oException->getCode());
            return $oResponse;
        }

        public function getCode(): int {
            return $this->iCode;
        }

        public function getMessage(): string {
            return $this->sMessage;
        }

        public function code(int $iCode):self {
            $this->iCode = $iCode;
            return $this;
        }

        public function message($sMessage):self {
            $this->sMessage = $sMessage;
            return $this;
        }
    }