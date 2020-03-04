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

    class IntegerArrayTest extends TestCase {
        /** @var array  */
        private $aPipeline = [];

        public function setUp(): void {
            parent::setUp();

            $oFullSpec = new FullSpec();
            $oFullSpec->addSpecFromInstance(new IntegerArrayTestClass());

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

        public function testIntegersOk(): void {
            $oResponse = $this->getResponse(['test' => '123,456']);
            $sResponse = $oResponse->getBody()->getContents();
            $aResponse = json_decode($sResponse, true);

            $this->assertIsArray($aResponse);
            $this->assertIsArray($aResponse['_request']);
            $this->assertIsArray($aResponse['_request']['params']);
            $this->assertIsArray($aResponse['_request']['params']['post']);
            $this->assertEquals("123,456", $aResponse['_request']['params']['post']['test']);
            $this->assertEquals(1, $aResponse['TEST_OK']);
        }

        public function testOneIntegerOneString(): void {
            $this->expectException(ValidationException::class);

            try {
                $this->getResponse(['test' => '123,abc']);
            } catch (ValidationException $e) {
                $aContext = $e->getContext();

                $this->assertIsArray($aContext);
                $this->assertCount(1, $aContext);
                $this->assertEquals('test[1]', $aContext[0]['property']);

                $this->assertEquals('type', $aContext[0]['constraint']);

                $this->assertStringContainsString('integer is required', $aContext[0]['message']);

                throw $e;
            }
        }

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
    }

    class IntegerArrayTestClass implements SpecInterface, MiddlewareInterface {
        public function spec(): Spec {
            return Spec::create()
                       ->httpMethod      (Method\POST)
                       ->path            ('/testing/post_params')
                       ->postParams      ([
                            'test' => Param\_Array::create()->items(Param\_Integer::create())
                                                            ->minItems(2)
                                                            ->maxItems(5)
                                                            ->uniqueItems(true)
                        ]);
        }

        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oResponse = ResponseBuilder::get($oRequest);
            $oResponse->set('TEST_OK', 1);

            return $oHandler->handle(ResponseBuilder::update($oRequest, $oResponse));
        }
    };