<?php
    namespace Enobrev\API\Exception;

    use Enobrev\API\HTTP;

    class MethodNotAllowed extends HttpErrorException {
        protected $code    = HTTP\METHOD_NOT_ALLOWED;

    }