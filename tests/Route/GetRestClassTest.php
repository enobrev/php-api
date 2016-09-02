<?php
    namespace Enobrev;

    require __DIR__ . '/../../vendor/autoload.php';

    use PHPUnit_Framework_TestCase as TestCase;

    use Enobrev\API\Response;
    use Enobrev\API\Request;
    use Enobrev\API\Route;

    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\Uri;

    class GetRestClassTest extends TestCase {

        const DOMAIN = 'example.com';

        public function testInit() {
            Route::init('\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', ['v1']);

            $this->assertEquals('Enobrev\\API\\Mock\\Table\\test', Route::_getNamespacedTableClassName('test'));
            $this->assertEquals('Enobrev\\API\\Mock\\v1\\test', Route::_getNamespacedAPIClassName('v1', 'test'));
        }

        public function testGetRestClassOverridden() {
            Route::init('\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', ['v1']);
            Response::init(self::DOMAIN);

            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('POST');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/addresses'));

            $oRequest = new Request($oServerRequest);
            $oRest = Route::_getRestClass($oRequest);

            $this->assertInstanceOf('\\Enobrev\\API\\Mock\\v1\\Address', $oRest);
        }

        public function testGetRestClassNotOverridden() {
            Route::init('\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', ['v1']);
            Response::init(self::DOMAIN);

            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('POST');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));

            $oRequest = new Request($oServerRequest);
            $oRest = Route::_getRestClass($oRequest);

            $this->assertInstanceOf('\\Enobrev\\API\\Rest', $oRest);
        }

        public function testGetRestClassAnything() {
            Route::init('\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', ['v1']);
            Response::init(self::DOMAIN);

            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('POST');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/anything'));

            $oRequest = new Request($oServerRequest);
            $oRest = Route::_getRestClass($oRequest);

            $this->assertInstanceOf('\\Enobrev\\API\\Rest', $oRest);
        }
    }