<?php
    namespace Enobrev\API;

    use Psr\Http\Message\ServerRequestInterface;

    trait RequestAttribute {
        protected static string $ATTRIBUTE_NAME = self::class;

        /**
         * Call this method from a public method to expose this attribute with the correct type
         *
         * @param ServerRequestInterface $oRequest
         * @param                        $mValue
         *
         * @return ServerRequestInterface
         */
        public static function setAttribute(ServerRequestInterface $oRequest, $mValue): ServerRequestInterface {
            return $oRequest->withAttribute(self::$ATTRIBUTE_NAME, $mValue);
        }

        /**
         * Call this method from a public method to expose this attribute with the correct type
         * @param ServerRequestInterface $oRequest
         * @return mixed
         */
        public static function getAttribute(ServerRequestInterface $oRequest) {
            return $oRequest->getAttribute(self::$ATTRIBUTE_NAME);
        }
    }