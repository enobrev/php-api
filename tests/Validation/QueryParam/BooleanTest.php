<?php /** @noinspection PhpUnhandledExceptionInspection */

    namespace Enobrev\Test\Validation\QueryParam;

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

    class BooleanTest extends TestCase {
        /** @var array  */
        private $aPipeline = [];

        public function setUp(): void {
            parent::setUp();

            $oFullSpec = new FullSpec();
            $oFullSpec->addSpecFromInstance(new BooleanTestClass());

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

        private function getResponse(array $aQueryParams): ResponseInterface {
            return Dispatcher::run($this->aPipeline, new ServerRequest(
                [],
                [],
                '/testing/query_params',
                Method\GET,
                'php://input',
                [],
                [],
                $aQueryParams
            ));
        }

        public function testTrue(): void {
            $oResponse = $this->getResponse(['test' => true]);
            $sResponse = $oResponse->getBody()->getContents();
            $aResponse = json_decode($sResponse, true);

            $this->assertIsArray($aResponse);
            $this->assertIsArray($aResponse['_request']);
            $this->assertIsArray($aResponse['_request']['params']);
            $this->assertIsArray($aResponse['_request']['params']['query']);
            $this->assertEquals(true, $aResponse['_request']['params']['query']['test']);
            $this->assertEquals(1, $aResponse['TEST_OK']);
        }

        public function testFalse(): void {
            $oResponse = $this->getResponse(['test' => false]);
            $sResponse = $oResponse->getBody()->getContents();
            $aResponse = json_decode($sResponse, true);

            $this->assertIsArray($aResponse);
            $this->assertIsArray($aResponse['_request']);
            $this->assertIsArray($aResponse['_request']['params']);
            $this->assertIsArray($aResponse['_request']['params']['query']);
            $this->assertEquals(false, $aResponse['_request']['params']['query']['test']);
            $this->assertEquals(1, $aResponse['TEST_OK']);
        }

        public function testOne(): void {
            $oResponse = $this->getResponse(['test' => 1]);
            $sResponse = $oResponse->getBody()->getContents();
            $aResponse = json_decode($sResponse, true);

            $this->assertIsArray($aResponse);
            $this->assertIsArray($aResponse['_request']);
            $this->assertIsArray($aResponse['_request']['params']);
            $this->assertIsArray($aResponse['_request']['params']['query']);
            $this->assertEquals(1, $aResponse['_request']['params']['query']['test']);
            $this->assertEquals(1, $aResponse['TEST_OK']);
        }

        public function testZero(): void {
            $oResponse = $this->getResponse(['test' => 0]);
            $sResponse = $oResponse->getBody()->getContents();
            $aResponse = json_decode($sResponse, true);

            $this->assertIsArray($aResponse);
            $this->assertIsArray($aResponse['_request']);
            $this->assertIsArray($aResponse['_request']['params']);
            $this->assertIsArray($aResponse['_request']['params']['query']);
            $this->assertEquals(0, $aResponse['_request']['params']['query']['test']);
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
                $this->assertStringContainsString('boolean is required', $aContext[0]['message']);

                throw $e;
            }
        }
    }

    class BooleanTestClass implements SpecInterface, MiddlewareInterface {
        public function spec(): Spec {
            return Spec::create()
                       ->httpMethod      (Method\GET)
                       ->path            ('/testing/query_params')
                       ->queryParams     ([
                            'test' => Param\_Boolean::create()->required()
                        ]);
        }

        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oResponse = ResponseBuilder::get($oRequest);
            $oResponse->set('TEST_OK', 1);

            return $oHandler->handle(ResponseBuilder::update($oRequest, $oResponse));
        }
    };