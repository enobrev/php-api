<?php
    namespace Enobrev\Test\Route;

    require __DIR__ . '/../../vendor/autoload.php';

    use Enobrev\API\Mock\Table\User;
    use PHPUnit_Framework_TestCase as TestCase;

    use Enobrev\API\Exception;
    use Enobrev\API\Response;
    use Enobrev\API\Request;
    use Enobrev\API\Route;
    use function Enobrev\dbg;

    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\Uri;

    class RouteTest extends TestCase {

        const DOMAIN = 'example.com';

        public function testInit() {
            Route::init('\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', ['v1']);

            $this->assertEquals('Enobrev\\API\\Mock\\Table\\test', Route::_getNamespacedTableClassName('test'));
            $this->assertEquals('Enobrev\\API\\Mock\\v1\\test', Route::_getNamespacedAPIClassName('v1', 'test'));

            Route::init('\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', ['v3']);
            $this->assertEquals('Enobrev\\API\\Mock\\v3\\test', Route::_getNamespacedAPIClassName('v3', 'test'));
        }

        public function test_getPrimaryTableFromPath() {
            Route::init('\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', ['v1']);
            Response::init(self::DOMAIN);

            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('POST');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));

            $oRequest = new Request($oServerRequest);
            $oTable   = Route::_getPrimaryTableFromPath($oRequest);

            $this->assertInstanceOf(User::class, $oTable);
        }

        public function test_getPrimaryTableFromInvalidPath() {
            Route::init('\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', ['v1']);
            Response::init(self::DOMAIN);

            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('POST');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/whatever'));

            $oRequest = new Request($oServerRequest);

            $this->expectException(Exception\InvalidTable::class);
            Route::_getPrimaryTableFromPath($oRequest);
        }
    }