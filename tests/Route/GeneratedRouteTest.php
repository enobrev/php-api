<?php
    namespace Enobrev\Test\Route;

    require __DIR__ . '/../../vendor/autoload.php';

    use Enobrev\API\Rest;
    use Enobrev\API\Request;
    use Enobrev\Log;
    use PHPUnit\Framework\TestCase;

    use Enobrev\API\Response;
    use Enobrev\API\Route;


    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\Uri;

    class GeneratedRouteTest extends TestCase {
        public const DOMAIN = 'example.com';

        public static function setUpBeforeClass():void {
            Log::setService('TEST');
            Route::init(__DIR__ . '/../Mock/API/', '\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', Rest::class, ['v1', 'v2']);
            Response::init(self::DOMAIN);

            // dbg(Route::_getCachedRoutes());
        }

        public function testRouteA(): void {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/test/methodA'));

            $oRequest  = new Request($oServerRequest);
            $oResponse = Route::_getResponse($oRequest);
            $oOutput   = $oResponse->getOutput();

            $this->assertObjectHasAttribute('test', $oOutput);
            $this->assertArrayHasKey('method', $oOutput->test);
            $this->assertArrayHasKey('a', $oOutput->test['method']);

            $this->assertEquals([1, 2, 3], $oOutput->test['method']['a']);
        }

        public function testRoute2B(): void {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/test2/methodB'));

            $oRequest  = new Request($oServerRequest);
            $oResponse = Route::_getResponse($oRequest);
            $oOutput   = $oResponse->getOutput();

            $this->assertObjectHasAttribute('test2', $oOutput);
            $this->assertArrayHasKey('method', $oOutput->test2);
            $this->assertArrayHasKey('b', $oOutput->test2['method']);

            $this->assertEquals([2, 3, 4], $oOutput->test2['method']['b']);
        }

        public function testRoute2BV2(): void {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v2/test2/methodB'));

            $oRequest  = new Request($oServerRequest);
            $oResponse = Route::_getResponse($oRequest);
            $oOutput   = $oResponse->getOutput();

            $this->assertObjectHasAttribute('test2', $oOutput);
            $this->assertArrayHasKey('v2', $oOutput->test2);
            $this->assertArrayHasKey('method', $oOutput->test2['v2']);
            $this->assertArrayHasKey('b', $oOutput->test2['v2']['method']);

            $this->assertEquals([4, 3, 2], $oOutput->test2['v2']['method']['b']);
        }
    }