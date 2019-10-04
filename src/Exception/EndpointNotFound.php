<?php
    namespace Enobrev\API\Exception;

    use Middlewares\Utils\HttpErrorException;

    use Enobrev\API\HTTP;

    class EndpointNotFound extends HttpErrorException {
        protected $code    = HTTP\NOT_FOUND;

    }