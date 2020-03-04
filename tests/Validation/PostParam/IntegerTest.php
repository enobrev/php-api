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


    class IntegerTest extends TestCase {
        /** @var array  */
        private $aPipeline = [];

        public function setUp(): void {
            parent::setUp();

            $oFullSpec = new FullSpec();
            $oFullSpec->addSpecFromInstance(new IntegerTestClass());

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
            $oResponse = $this->getResponse(['test' => '123']);
            $sResponse = $oResponse->getBody()->getContents();
            $aResponse = json_decode($sResponse, true);

            $this->assertIsArray($aResponse);
            $this->assertIsArray($aResponse['_request']);
            $this->assertIsArray($aResponse['_request']['params']);
            $this->assertIsArray($aResponse['_request']['params']['post']);
            $this->assertEquals("123", $aResponse['_request']['params']['post']['test']);
            $this->assertEquals(1, $aResponse['TEST_OK']);
        }

        public function testNullable(): void {
            $oResponse = $this->getResponse(['test' => null]);
            $sResponse = $oResponse->getBody()->getContents();
            $aResponse = json_decode($sResponse, true);

            $this->assertIsArray($aResponse);
            $this->assertIsArray($aResponse['_request']);
            $this->assertIsArray($aResponse['_request']['params']);
            $this->assertIsArray($aResponse['_request']['params']['post']);
            $this->assertEquals(null, $aResponse['_request']['params']['post']['test']);
            $this->assertEquals(1, $aResponse['TEST_OK']);

        }

        public function testString(): void {
            $this->expectException(ValidationException::class);

            try {
                $this->getResponse(['test' => 'abcdef']);
            } catch (ValidationException $e) {
                $aContext = $e->getContext();
                $this->assertIsArray($aContext);
                $this->assertCount(1, $aContext);
                $this->assertEquals('test', $aContext[0]['property']);
                $this->assertEquals('type', $aContext[0]['constraint']);
                $this->assertStringContainsString('integer or a null is required', $aContext[0]['message']);

                throw $e;
            }
        }

        public function testMinimum(): void {
            $this->expectException(ValidationException::class);

            try {
                $this->getResponse(['test' => '5']);
            } catch (ValidationException $e) {
                $aContext = $e->getContext();

                $this->assertIsArray($aContext);
                $this->assertCount(1, $aContext);
                $this->assertEquals('test', $aContext[0]['property']);
                $this->assertEquals('minimum', $aContext[0]['constraint']);
                $this->assertStringContainsString('Must have a minimum value', $aContext[0]['message']);

                throw $e;
            }
        }

        public function testMaximum(): void {
            $this->expectException(ValidationException::class);

            try {
                $this->getResponse(['test' => '5000']);
            } catch (ValidationException $e) {
                $aContext = $e->getContext();

                $this->assertIsArray($aContext);
                $this->assertCount(1, $aContext);
                $this->assertEquals('test', $aContext[0]['property']);
                $this->assertEquals('maximum', $aContext[0]['constraint']);
                $this->assertStringContainsString('Must have a maximum value', $aContext[0]['message']);

                throw $e;
            }
        }
    }

    class IntegerTestClass implements SpecInterface, MiddlewareInterface {
        public function spec(): Spec {
            return Spec::create()
                       ->httpMethod      (Method\POST)
                       ->path            ('/testing/post_params')
                       ->postParams     ([
                            'test' => Param\_Integer::create()->required()->nullable()->minimum(10)->maximum(1000)
                        ]);
        }

        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oResponse = ResponseBuilder::get($oRequest);
            $oResponse->set('TEST_OK', 1);

            return $oHandler->handle(ResponseBuilder::update($oRequest, $oResponse));
        }
    };