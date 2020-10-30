<?php
declare(strict_types = 1);

namespace Enobrev\API\Exception;

use Exception;
use RuntimeException;
use Throwable;

use Enobrev\API\HTTP;

/**
 * Class HttpErrorException
 *
 * replaces middleware/utils/http-error-exception which did not allow our custom https response codes
 * @package Enobrev\API\Exception
 */
class HttpErrorException extends Exception {

    private $aContext = [];

    /**
     * Create and returns a new instance
     *
     * @param int            $iCode A valid http error code
     * @param array          $aContext
     * @param Throwable|null $oPrevious
     *
     * @return static
     */
    public static function create(int $iCode = 500, array $aContext = [], Throwable $oPrevious = null): self {
        if (!isset(HTTP\TEXT[$iCode])) {
            throw new RuntimeException("Http error not valid ({$iCode})");
        }

        $oException = new static(HTTP\TEXT[$iCode], $iCode, $oPrevious);
        $oException->setContext($aContext);

        return $oException;
    }

    /**
     * Add data context used in the error handler
     * @param array $aContext
     */
    public function setContext(array $aContext) {
        $this->aContext = $aContext;
    }

    /**
     * Return the data context
     */
    public function getContext(): array {
        return $this->aContext;
    }
}
