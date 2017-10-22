<?php
    namespace Enobrev\Test\Route;

    require __DIR__ . '/../../vendor/autoload.php';

    use Enobrev\API\DataMap;
    use Enobrev\API\Rest;
    use Enobrev\Log;
    use PHPUnit_Framework_TestCase as TestCase;

    use Enobrev\API\Response;
    use Enobrev\API\Request;
    use Enobrev\API\Route;

    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\Uri;

    class GetRestClassTest extends TestCase {

        const DOMAIN = 'example.com';

        public static function setUpBeforeClass() {
            Log::setService('TEST');
            Route::init(__DIR__ . '/../Mock/API/', '\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', Rest::class, ['v1']);
            Response::init(self::DOMAIN);
            DataMap::setDataFile(__DIR__ . '/../Mock/DataMap.json');
        }

        public function testInit() {
            $this->assertEquals('Enobrev\\API\\Mock\\Table\\test', Rest::_getNamespacedTableClassName('test'));
            $this->assertEquals('Enobrev\\API\\Mock\\v1\\test', Route::_getNamespacedAPIClassName('v1', 'test'));
        }

        public function testGetRestClassOverridden() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('POST');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/addresses'));

            $oRequest = new Request($oServerRequest);
            $oRest = Route::_getRestClass($oRequest);

            $this->assertInstanceOf('\\Enobrev\\API\\Mock\\v1\\Address', $oRest);
        }

        public function testGetRestClassNotOverridden() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('POST');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));

            $oRequest = new Request($oServerRequest);
            $oRest = Route::_getRestClass($oRequest);

            $this->assertInstanceOf('\\Enobrev\\API\\Rest', $oRest);
        }

        public function testGetRestClassAnything() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('POST');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/anything'));

            $oRequest = new Request($oServerRequest);
            $oRest = Route::_getRestClass($oRequest);

            $this->assertInstanceOf('\\Enobrev\\API\\Rest', $oRest);
        }
    }