<?php
    namespace Enobrev\Test\Route;

    require __DIR__ . '/../vendor/autoload.php';

    use Enobrev\API\Request;
    use Enobrev\Log;
    use PHPUnit\Framework\TestCase;
    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\Stream;
    use Zend\Diactoros\Uri;

    Log::setService('RequestTest');

    class RequestTest extends TestCase {
        public function testGet() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://example.com/testing'));
            $oServerRequest = $oServerRequest->withQueryParams(['test' => 'TEST']);

            $oRequest = new Request($oServerRequest);

            $this->assertTrue($oRequest->isGet());
            $this->assertFalse($oRequest->isPost());
            $this->assertFalse($oRequest->isPut());
            $this->assertFalse($oRequest->isOptions());

            $this->assertCount(2,            $oRequest->Path);
            $this->assertEquals('v1',        $oRequest->Path[0]);
            $this->assertEquals('testing',   $oRequest->Path[1]);
            $this->assertEquals('json',      $oRequest->Format);

            $this->assertArrayHasKey('test', $oRequest->GET);
            $this->assertEquals('TEST',      $oRequest->GET['test']);
        }

        public function testGetJPG() {
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://example.com/testing.jpg'));

            $oRequest = new Request($oServerRequest);

            $this->assertEquals('jpg',      $oRequest->Format);
        }

        public function testGetRoot() {
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://example.com/'));

            $oRequest = new Request($oServerRequest);

            $this->assertTrue($oRequest->pathIsRoot());
        }

        public function testGetWithAttributes() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://example.com/testing'));
            $oServerRequest = $oServerRequest->withAttribute('test', 'TEST');
            $oServerRequest = $oServerRequest->withAttribute('test2', 'TEST2');

            $oRequest = new Request($oServerRequest);

            $this->assertEquals('TEST', $oRequest->paramFromUriPath('test'));
            $this->assertEquals('TEST2', $oRequest->paramFromUriPath('test2'));

            $oRequest->updatePathParams(['test' => 'TEST!!!', 'test2' => 'TEST2!!!']);

            $this->assertEquals('TEST!!!', $oRequest->paramFromUriPath('test'));
            $this->assertEquals('TEST2!!!', $oRequest->paramFromUriPath('test2'));
        }

        public function testPost() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('POST');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://example.com/testing'));
            $oServerRequest = $oServerRequest->withParsedBody(['test' => 'TEST']);

            $oRequest = new Request($oServerRequest);

            $this->assertFalse($oRequest->isGet());
            $this->assertTrue($oRequest->isPost());
            $this->assertFalse($oRequest->isPut());
            $this->assertFalse($oRequest->isOptions());

            $this->assertCount(2,            $oRequest->Path);
            $this->assertEquals('v1',        $oRequest->Path[0]);
            $this->assertEquals('testing',   $oRequest->Path[1]);
            $this->assertEquals('json',      $oRequest->Format);

            $this->assertArrayHasKey('test', $oRequest->POST);
            $this->assertEquals('TEST',      $oRequest->POST['test']);
        }

        public function testPost__Json() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('POST');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://example.com/testing'));
            $oServerRequest = $oServerRequest->withParsedBody(['__json' => json_encode(['test' => 'TEST'])]);

            $oRequest = new Request($oServerRequest);

            $this->assertArrayHasKey('test', $oRequest->POST);
            $this->assertEquals('TEST',      $oRequest->POST['test']);
        }

        public function testPostApplicationJson() {

            $sBody   = json_encode(['test' => 'TEST']);
            $oStream = new Stream('php://memory', 'wb+');
            $oStream->write($sBody);

            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('POST');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://example.com/testing'));
            $oServerRequest = $oServerRequest->withHeader('Content-Type', 'application/json');
            $oServerRequest = $oServerRequest->withBody($oStream);

            $oRequest = new Request($oServerRequest);

            $this->assertArrayHasKey('test', $oRequest->POST);
            $this->assertEquals('TEST',      $oRequest->POST['test']);
        }

        public function testPut() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('PUT');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://example.com/testing'));

            $oRequest = new Request($oServerRequest);

            $this->assertFalse($oRequest->isGet());
            $this->assertFalse($oRequest->isPost());
            $this->assertTrue($oRequest->isPut());
            $this->assertFalse($oRequest->isOptions());

            $this->assertCount(2,            $oRequest->Path);
            $this->assertEquals('v1',        $oRequest->Path[0]);
            $this->assertEquals('testing',   $oRequest->Path[1]);
            $this->assertEquals('json',      $oRequest->Format);
        }

        public function testPutFormEncoded() {
            $sBody   = 'test=TEST';
            $oStream = new Stream('php://memory', 'wb+');
            $oStream->write($sBody);

            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('PUT');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://example.com/testing'));
            $oServerRequest = $oServerRequest->withHeader('Content-Type', 'application/x-www-form-urlencoded');
            $oServerRequest = $oServerRequest->withBody($oStream);

            $oRequest = new Request($oServerRequest);

            $this->assertArrayHasKey('test', $oRequest->PUT);
            $this->assertEquals('TEST',      $oRequest->PUT['test']);
        }

        public function testOptions() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('OPTIONS');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://example.com/testing'));

            $oRequest = new Request($oServerRequest);

            $this->assertFalse($oRequest->isGet());
            $this->assertFalse($oRequest->isPost());
            $this->assertFalse($oRequest->isPut());
            $this->assertTrue($oRequest->isOptions());

            $this->assertCount(2,            $oRequest->Path);
            $this->assertEquals('v1',        $oRequest->Path[0]);
            $this->assertEquals('testing',   $oRequest->Path[1]);
            $this->assertEquals('json',      $oRequest->Format);
        }
    }