<?php
    namespace Enobrev\API\Exception;

    use Enobrev\API\HTTP;

    class EndpointNotFound extends HttpErrorException {
        protected $code    = HTTP\NOT_FOUND;

    }