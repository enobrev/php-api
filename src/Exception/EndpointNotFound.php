<?php
    namespace Enobrev\API\Exception;

    use Middlewares\HttpErrorException;

    use Enobrev\API\HTTP;

    class EndpointNotFound extends HttpErrorException {
        protected $code    = HTTP\NOT_FOUND;

    }