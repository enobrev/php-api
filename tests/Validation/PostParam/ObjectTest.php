<?php /** @noinspection PhpUnhandledExceptionInspection */

    namespace Enobrev\Test\Validation\PostParam;

    require __DIR__ . '/../../../vendor/autoload.php';

    use Laminas\Diactoros\ServerRequest;
    use Middlewares\Utils\Dispatcher;
    use PHPUnit\Framework\TestCase;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\Exception\ValidationException;
    use Enobrev\API\FullSpec;
    use Enobrev\API\Method;
    use Enobrev\API\Middleware\FastRoute;
    use Enobrev\API\Middleware\Request\AttributeFullSpecRoutes;
    use Enobrev\API\Middleware\Request\AttributeSpec;
    use Enobrev\API\Middleware\Request\CoerceParams;
    use Enobrev\API\Middleware\RequestHandler;
    use Enobrev\API\Middleware\Response\MetadataRequest;
    use Enobrev\API\Middleware\ResponseBuilderDone;
    use Enobrev\API\Middleware\ResponseBuilder;
    use Enobrev\API\Middleware\ValidateSpec;
    use Enobrev\API\Param;
    use Enobrev\API\Spec;
    use Enobrev\API\SpecInterface;
    use function Enobrev\dbg;

    class ObjectTest extends TestCase {
        /** @var array  */
        private $aPipeline = [];

        public function setUp(): void {
            parent::setUp();

            $oFullSpec = new FullSpec();
            $oFullSpec->addSpecFromInstance(new ObjectTestClass());

            $this->aPipeline = [
                new ResponseBuilder(),
                new MetadataRequest(),
                new AttributeFullSpecRoutes($oFullSpec),
                new FastRoute(),
                new AttributeSpec(),
                new CoerceParams(),
                new ValidateSpec(),
                new RequestHandler(),
                new ResponseBuilderDone()
            ];
        }

        private function getResponse(array $aPostBody): ResponseInterface {
            return Dispatcher::run($this->aPipeline, new ServerRequest(
                [],
                [],
                '/testing/post_params',
                Method\POST,
                'php://input',
                [],
                [],
                [],
                $aPostBody
            ));
        }

        public function testOk(): void {
            $oResponse = $this->getResponse([
                'test' => [
                    'test_string'   => 'abc123',
                    'test_integer'  => 123,
                    'test_number'   => 123.45,
                    'test_boolean'  => true,
                    'test_array'    => '123,456',
                    'test_object'   => [
                        'nested' => 'ghijkl'
                    ]
                ]
            ]);
            $sResponse = $oResponse->getBody()->getContents();
            $aResponse = json_decode($sResponse, true);

            $this->assertIsArray($aResponse);
            $this->assertIsArray($aResponse['_request']);
            $this->assertIsArray($aResponse['_request']['params']);
            $this->assertIsArray($aResponse['_request']['params']['post']);
            $this->assertIsArray($aResponse['_request']['params']['post']['test']);
            $this->assertIsArray($aResponse['_request']['params']['post']['test']['test_object']);
            $this->assertEquals("abc123",   $aResponse['_request']['params']['post']['test']['test_string']);
            $this->assertEquals(123,        $aResponse['_request']['params']['post']['test']['test_integer']);
            $this->assertEquals(123.45,     $aResponse['_request']['params']['post']['test']['test_number']);
            $this->assertEquals(true,       $aResponse['_request']['params']['post']['test']['test_boolean']);
            $this->assertEquals('123,456',  $aResponse['_request']['params']['post']['test']['test_array']);
            $this->assertEquals('ghijkl',   $aResponse['_request']['params']['post']['test']['test_object']['nested']);

            $this->assertEquals(1, $aResponse['TEST_OK']);
        }

        public function testRequired(): void {
            $this->expectException(ValidationException::class);

            try {
                $this->getResponse([
                    'test' => [
                        'test_string'   => 'abc123'
                    ]
                ]);
            } catch (ValidationException $e) {
                $aContext = $e->getContext();

                $this->assertIsArray($aContext);
                $this->assertCount(1, $aContext);
                $this->assertEquals('test.test_integer', $aContext[0]['property']);
                $this->assertEquals('required', $aContext[0]['constraint']);
                $this->assertStringContainsString('is required', $aContext[0]['message']);

                throw $e;
            }
        }

        public function testOneIntegerOneString(): void {
            $this->expectException(ValidationException::class);

            try {
                $this->getResponse([
                    'test' => [
                        'test_integer'   => 123,
                        'test_array'     => '123,abc'
                    ]
                ]);
            } catch (ValidationException $e) {
                $aContext = $e->getContext();

                $this->assertIsArray($aContext);
                $this->assertCount(1, $aContext);
                $this->assertEquals('test.test_array[1]', $aContext[0]['property']);

                $this->assertEquals('type', $aContext[0]['constraint']);

                $this->assertStringContainsString('integer is required', $aContext[0]['message']);

                throw $e;
            }
        }
        /*

        public function testTwoStrings(): void {
            $this->expectException(ValidationException::class);

            try {
                $this->getResponse(['test' => 'abc,def']);
            } catch (ValidationException $e) {
                $aContext = $e->getContext();

                $this->assertIsArray($aContext);
                $this->assertCount(2, $aContext);
                $this->assertEquals('test[0]', $aContext[0]['property']);
                $this->assertEquals('test[1]', $aContext[1]['property']);

                $this->assertEquals('type', $aContext[0]['constraint']);
                $this->assertEquals('type', $aContext[1]['constraint']);

                $this->assertStringContainsString('integer is required', $aContext[0]['message']);
                $this->assertStringContainsString('integer is required', $aContext[1]['message']);

                throw $e;
            }
        }

        public function testMinItems(): void {
            $this->expectException(ValidationException::class);

            try {
                $this->getResponse(['test' => '123']);
            } catch (ValidationException $e) {
                $aContext = $e->getContext();

                $this->assertIsArray($aContext);
                $this->assertCount(1, $aContext);
                $this->assertEquals('test', $aContext[0]['property']);
                $this->assertEquals('minItems', $aContext[0]['constraint']);
                $this->assertEquals(2, $aContext[0]['minItems']);

                $this->assertStringContainsString('must be a minimum of', $aContext[0]['message']);

                throw $e;
            }
        }

        public function testMaxItems(): void {
            $this->expectException(ValidationException::class);

            try {
                $this->getResponse(['test' => '123,234,345,456,567,678']);
            } catch (ValidationException $e) {
                $aContext = $e->getContext();

                $this->assertIsArray($aContext);
                $this->assertCount(1, $aContext);
                $this->assertEquals('test', $aContext[0]['property']);
                $this->assertEquals('maxItems', $aContext[0]['constraint']);
                $this->assertEquals(5, $aContext[0]['maxItems']);

                $this->assertStringContainsString('must be a maximum of', $aContext[0]['message']);

                throw $e;
            }
        }

        public function testUniqueItems(): void {
            $this->expectException(ValidationException::class);

            try {
                $this->getResponse(['test' => '123,123']);
            } catch (ValidationException $e) {
                $aContext = $e->getContext();

                $this->assertIsArray($aContext);
                $this->assertCount(1, $aContext);
                $this->assertEquals('test', $aContext[0]['property']);
                $this->assertEquals('uniqueItems', $aContext[0]['constraint']);

                $this->assertStringContainsString('no duplicates allowed', $aContext[0]['message']);

                throw $e;
            }
        }
        */
    }

    class ObjectTestClass implements SpecInterface, MiddlewareInterface {
        public function spec(): Spec {
            return Spec::create()
                       ->httpMethod      (Method\POST)
                       ->path            ('/testing/post_params')
                       ->postParams      ([
                            'test' => Param\_Object::create()->items([
                                'test_string'   => Param\_String::create(),
                                'test_integer'  => Param\_Integer::create()->required(),
                                'test_number'   => Param\_Number::create(),
                                'test_boolean'  => Param\_Boolean::create(),
                                'test_array'    => Param\_Array::create()->items(Param\_Integer::create()),
                                'test_object'   => Param\_Object::create()->items([
                                    'nested' => Param\_String::create()
                                ])
                            ])
                        ]);
        }

        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oResponse = ResponseBuilder::get($oRequest);
            $oResponse->set('TEST_OK', 1);

            return $oHandler->handle(ResponseBuilder::update($oRequest, $oResponse));
        }
    };