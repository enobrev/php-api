<?php
    namespace Enobrev\API\Exception;

    use Middlewares\HttpErrorException;

    use Enobrev\API\HTTP;

    class MethodNotAllowed extends HttpErrorException {
        protected $code    = HTTP\METHOD_NOT_ALLOWED;

    }