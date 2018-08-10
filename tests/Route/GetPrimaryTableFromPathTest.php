<?php
    namespace Enobrev\Test\Route;

    require __DIR__ . '/../../vendor/autoload.php';

    use Enobrev\API\DataMap;
    use Enobrev\API\Rest;
    use Enobrev\Log;
    use PHPUnit\Framework\TestCase;

    use Enobrev\API\Exception;
    use Enobrev\API\Response;
    use Enobrev\API\Request;
    use Enobrev\API\Route;
    use Enobrev\API\Mock\Table\User;

    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\Uri;

    class GetPrimaryTableFromPathTest extends TestCase {

        const DOMAIN = 'example.com';

        public static function setUpBeforeClass() {
            Log::setService('TEST');
            Route::init(__DIR__ . '/../Mock/API/', '\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', Rest::class, ['v1']);
            Response::init(self::DOMAIN);
            DataMap::setDataFile(__DIR__ . '/../Mock/DataMap.json');
        }

        public function test_getPrimaryTableFromPath() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('POST');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));

            $oRequest = new Request($oServerRequest);
            $oRest    = new Rest($oRequest);
            $oTable   = $oRest->_getPrimaryTableFromPath();

            $this->assertInstanceOf(User::class, $oTable);
        }

        public function test_getPrimaryTableFromInvalidPath() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('POST');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/whatever'));

            $oRequest = new Request($oServerRequest);

            $this->expectException(Exception\InvalidTable::class);

            $oRest = new Rest($oRequest);
            $oRest->_getPrimaryTableFromPath();
        }
    }