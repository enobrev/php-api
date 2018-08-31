<?php
    namespace Enobrev\API;

    use Psr\Http\Message\ServerRequestInterface;

    interface RequestAttributeInterface {
        public static function setAttribute(ServerRequestInterface $oRequest, $mValue): ServerRequestInterface;

        public static function getAttribute(ServerRequestInterface $oRequest);
    }