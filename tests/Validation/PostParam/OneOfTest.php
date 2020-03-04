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

    class OneOfTest extends TestCase {
        /** @var array  */
        private $aPipeline = [];

        public function setUp(): void {
            parent::setUp();

            $oFullSpec = new FullSpec();
            $oFullSpec->addSpecFromInstance(new OneOfTestClass());

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
                '/testing/one_of',
                Method\POST,
                'php://input',
                [],
                [],
                [],
                $aPostBody
            ));
        }

        public function testMatch1(): void {
            $oResponse = $this->getResponse([
                'id1'       => 'abc',
                'test_type' => 'test_type_1'
            ]);
            $sResponse = $oResponse->getBody()->getContents();
            $aResponse = json_decode($sResponse, true);

            $this->assertIsArray($aResponse);
            $this->assertIsArray($aResponse['_request']);
            $this->assertIsArray($aResponse['_request']['params']);
            $this->assertIsArray($aResponse['_request']['params']['post']);
            $this->assertEquals('abc',          $aResponse['_request']['params']['post']['id1']);
            $this->assertEquals('test_type_1',  $aResponse['_request']['params']['post']['test_type']);
            $this->assertEquals(1, $aResponse['TEST_OK']);
        }

        public function testMatch2(): void {
            $oResponse = $this->getResponse([
                'id2'       => 'abc',
                'test_type' => 'test_type_2'
            ]);
            $sResponse = $oResponse->getBody()->getContents();
            $aResponse = json_decode($sResponse, true);

            $this->assertIsArray($aResponse);
            $this->assertIsArray($aResponse['_request']);
            $this->assertIsArray($aResponse['_request']['params']);
            $this->assertIsArray($aResponse['_request']['params']['post']);
            $this->assertEquals('abc',          $aResponse['_request']['params']['post']['id2']);
            $this->assertEquals('test_type_2',  $aResponse['_request']['params']['post']['test_type']);
            $this->assertEquals(1, $aResponse['TEST_OK']);
        }

        public function testMatch3(): void {
            $oResponse = $this->getResponse([
                'id3'       => 'abc',
                'test_type' => 'test_type_3'
            ]);
            $sResponse = $oResponse->getBody()->getContents();
            $aResponse = json_decode($sResponse, true);

            $this->assertIsArray($aResponse);
            $this->assertIsArray($aResponse['_request']);
            $this->assertIsArray($aResponse['_request']['params']);
            $this->assertIsArray($aResponse['_request']['params']['post']);
            $this->assertEquals('abc',          $aResponse['_request']['params']['post']['id3']);
            $this->assertEquals('test_type_3',  $aResponse['_request']['params']['post']['test_type']);
            $this->assertEquals(1, $aResponse['TEST_OK']);
        }

        public function testMatchNonExisting(): void {
            $this->expectException(ValidationException::class);

            try {
                $this->getResponse([
                    'id4'       => 'abc',
                    'test_type' => 'test_type_4'
                ]);
            } catch (ValidationException $e) {
                $aContext = $e->getContext();

                $this->assertIsArray($aContext);
                $this->assertCount(1, $aContext);
                $this->assertEquals('test_type', $aContext[0]['property']);
                $this->assertEquals('discriminator', $aContext[0]['constraint']);
                $this->assertStringContainsString('Discriminator value did not match any available schemas', $aContext[0]['message']);

                throw $e;
            }
        }

        public function testMisMatch3(): void {
            $this->expectException(ValidationException::class);

            try {
                $this->getResponse([
                    'id2'       => 'abc',
                    'test_type' => 'test_type_3'
                ]);
            } catch (ValidationException $e) {
                $aContext = $e->getContext();

                $this->assertIsArray($aContext);
                $this->assertCount(2, $aContext);
                $this->assertEquals('id3',      $aContext[0]['property']);
                $this->assertEquals('required', $aContext[0]['constraint']);
                $this->assertEquals('additionalProp', $aContext[1]['constraint']);

                throw $e;
            }
        }
    }

    class OneOfTestClass implements SpecInterface, MiddlewareInterface {
        public function spec(): Spec {
            return Spec::create()
                ->httpMethod        (Method\POST)
                ->path              ('/testing/one_of')
                ->postBodyRequest(
                    FullSpec\Component\Request::create('testing')
                        ->description('Test Request')
                        ->discriminator('test_type')
                        ->post(
                            FullSpec\Component\Schema::create('test_types')->oneOf(
                                [
                                    FullSpec\Component\Schema::create('test_type_1')->title('Test Type 1')->schema(
                                        [

                                            'id1'        => Param\_String::create()->required(),
                                            'test_type'  => Param\_String::create()->required()->enum(['test_type_1'])
                                        ]
                                    ),
                                    FullSpec\Component\Schema::create('test_type_2')->title('Test Type 2')->schema(
                                        [

                                            'id2'        => Param\_String::create()->required(),
                                            'test_type'  => Param\_String::create()->required()->enum(['test_type_2'])
                                        ]
                                    ),
                                    FullSpec\Component\Schema::create('test_type_3')->title('Test Type 3')->schema(
                                        [

                                            'id3'        => Param\_String::create()->required(),
                                            'test_type'  => Param\_String::create()->required()->enum(['test_type_3'])
                                        ]
                                    ),
                                ]
                            )
                        )
                );
        }

        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oResponse = ResponseBuilder::get($oRequest);
            $oResponse->set('TEST_OK', 1);

            return $oHandler->handle(ResponseBuilder::update($oRequest, $oResponse));
        }
    };