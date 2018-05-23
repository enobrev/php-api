<?php
    namespace Enobrev\API;

    use Psr\Http\Message\StreamInterface;
    use SplFileInfo;
    use Zend\Diactoros\Response;
    use Zend\Diactoros\Stream;

    /**
     * ZendAttachmentResponse.
     *
     * Allows creating a response by passing file data to the constructor; by default,
     * reads the data, sets a status code of 200 and sets the
     * Content-Type header to what's passed in.
     */
    class ZendAttachmentResponse extends Response {
        use Response\InjectContentTypeTrait;

        /**
         * ZendFileResponse constructor.
         * @param StreamInterface|resource|string $sFile
         * @param int $iStatus
         * @param array $aHeaders
         * @psalm-suppress ImplicitToStringCast
         * @psalm-suppress PossiblyInvalidArgument
         */
        public function __construct(string $sFile, int $iStatus = 200, array $aHeaders = []) {
            $oFileInfo = new SplFileInfo($sFile);

            $sRealPath = $oFileInfo->getRealPath();
            $sFileName = $oFileInfo->getFilename();
            $iSize     = $oFileInfo->getSize();

            $aHeaders = array_merge($aHeaders, [
                'content-length'        => $iSize,
                'content-disposition'   => "attachment; filename=$sFileName"
            ]);

            $oStream = new Stream($sRealPath, 'r');
            parent::__construct($oStream, $iStatus, $aHeaders);

        }
    }
