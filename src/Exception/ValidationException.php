<?php
    namespace Enobrev\API\Exception;

    use Enobrev\API\HTTP;

    class ValidationException extends HttpErrorException {
        protected $code    = HTTP\BAD_REQUEST;

    }