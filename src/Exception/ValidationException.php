<?php
    namespace Enobrev\API\Exception;

    use Middlewares\HttpErrorException;

    use Enobrev\API\HTTP;

    class ValidationException extends HttpErrorException {
        protected $code    = HTTP\BAD_REQUEST;

    }