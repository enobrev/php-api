<?php
    namespace Enobrev\Test\Route;

    require __DIR__ . '/../../vendor/autoload.php';

    use Enobrev\API\DataMap;
    use Enobrev\API\Rest;
    use Enobrev\Log;
    use PHPUnit\Framework\TestCase;

    use Enobrev\API\Response;
    use Enobrev\API\Request;
    use Enobrev\API\Route;

    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\Uri;
    use Enobrev\API\Mock\v1\Address;

    class GetRestClassTest extends TestCase {

        public const DOMAIN = 'example.com';

        public static function setUpBeforeClass():void {
            Log::setService('TEST');
            Route::init(__DIR__ . '/../Mock/API/', '\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\');
            Response::init(self::DOMAIN);
            DataMap::setDataFile(__DIR__ . '/../Mock/DataMap.json');
        }

        public function testInit(): void {
            $this->assertEquals('Enobrev\\API\\Mock\\Table\\test', Rest::_getNamespacedTableClassName('test'));
            $this->assertEquals('Enobrev\\API\\Mock\\v1\\test', Route::_getNamespacedAPIClassName('v1', 'test'));
        }

        public function testGetRestClassOverridden(): void {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('POST');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/addresses'));

            $oRequest = new Request($oServerRequest);
            $oRest = Route::_getRestClass($oRequest);

            $this->assertInstanceOf(Address::class, $oRest);
        }

        public function testGetRestClassNotOverridden(): void {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('POST');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));

            $oRequest = new Request($oServerRequest);
            $oRest = Route::_getRestClass($oRequest);

            $this->assertInstanceOf(Rest::class, $oRest);
        }

        public function testGetRestClassAnything(): void {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('POST');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/anything'));

            $oRequest = new Request($oServerRequest);
            $oRest = Route::_getRestClass($oRequest);

            $this->assertInstanceOf(Rest::class, $oRest);
        }
    }