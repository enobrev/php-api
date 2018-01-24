<?php
    namespace Enobrev\API;

    use Zend\Diactoros\Response;
    use Zend\Diactoros\Stream;

    /**
     * ZendFileResponse.
     *
     * Allows creating a response by passing file data to the constructor; by default,
     * reads the data, sets a status code of 200 and sets the
     * Content-Type header to what's passed in.
     */
    class ZendFileResponse extends Response {
        use Response\InjectContentTypeTrait;

        /**
         * ZendFileResponse constructor.
         * @param \Psr\Http\Message\StreamInterface|resource|string $sFile
         * @param int $iStatus
         * @param array $aHeaders
         * @psalm-suppress ImplicitToStringCast
         * @psalm-suppress PossiblyInvalidArgument
         */
        public function __construct($sFile, $iStatus = 200, array $aHeaders = []) {
            $oBody = new Stream('php://temp', 'wb+');
            $oBody->write(file_get_contents($sFile));
            $oBody->rewind();

            parent::__construct($oBody, $iStatus, $aHeaders);
        }
    }
