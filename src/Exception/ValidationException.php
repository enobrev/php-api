<?php
    namespace Enobrev\API\Exception;

    use Middlewares\Utils\HttpErrorException;

    use Enobrev\API\HTTP;

    class ValidationException extends HttpErrorException {
        protected $code    = HTTP\BAD_REQUEST;

    }