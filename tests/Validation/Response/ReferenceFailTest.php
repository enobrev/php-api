<?php /** @noinspection PhpUnhandledExceptionInspection */

    namespace Enobrev\Test\Validation\Response;

    require __DIR__ . '/../../../vendor/autoload.php';

    use Enobrev\API\Exception\ValidationException;
    use Laminas\Diactoros\ServerRequest;
    use Middlewares\Utils\Dispatcher;
    use PHPUnit\Framework\TestCase;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\FullSpec;
    use Enobrev\API\FullSpec\Component\Schema;
    use Enobrev\API\FullSpec\Component\Reference;
    use Enobrev\API\FullSpec\Component\Response;
    use Enobrev\API\HTTP;
    use Enobrev\API\Method;
    use Enobrev\API\Middleware\FastRoute;
    use Enobrev\API\Middleware\Request\AttributeFullSpecRoutes;
    use Enobrev\API\Middleware\Request\AttributeSpec;
    use Enobrev\API\Middleware\Request\CoerceParams;
    use Enobrev\API\Middleware\RequestHandler;
    use Enobrev\API\Middleware\Response\ValidateResponse;
    use Enobrev\API\Middleware\ResponseBuilderDone;
    use Enobrev\API\Middleware\ResponseBuilder;
    use Enobrev\API\Param;
    use Enobrev\API\Spec;
    use Enobrev\API\SpecInterface;
    use function Enobrev\dbg;
    use function Laminas\Stratigility\path;

    class ReferenceFailTest extends TestCase {
        /** @var array  */
        private $aPipeline = [];

        public function setUp(): void {
            parent::setUp();

            $oFullSpec = FullSpec::getInstance();
            $oFullSpec->addSpecFromInstance(new ReferenceFailTestClass());
            $oFullSpec->addComponentList(new ReferenceFailComponent());

            $this->aPipeline = [
                new ResponseBuilder(),
                new AttributeFullSpecRoutes($oFullSpec),
                new FastRoute(),
                new AttributeSpec(),
                new CoerceParams(),
                new RequestHandler(),
                new ValidateResponse(),
                new ResponseBuilderDone()
            ];
        }

        private function getResponse(): ResponseInterface {
            return Dispatcher::run($this->aPipeline, new ServerRequest(
                [],
                [],
                '/testing/response',
                Method\GET
            ));
        }

        public function testFail(): void {
            $this->expectException(ValidationException::class);

            try {
                $this->getResponse();
            } catch (ValidationException $e) {
                $aContext = $e->getContext();

                $this->assertIsArray($aContext);
                $this->assertCount(2, $aContext);
                $this->assertEquals('REFERENCE_OK', $aContext[0]['property']);
                $this->assertEquals('TEST_OK',      $aContext[1]['property']);

                $this->assertEquals('maximum',      $aContext[0]['constraint']);
                $this->assertEquals('maximum',      $aContext[1]['constraint']);

                throw $e;
            }
        }
    }
    
    class ReferenceFailComponent implements FullSpec\ComponentListInterface {
        public function components(): array {
            return [
                Schema::create(Schema::PREFIX . '/test')->schema(
                    [
                        'REFERENCE_OK' => Param\_Integer::create()->minimum(1)->maximum(1)
                    ]
                ),
                Response::create(Response::PREFIX . '/test')
                    ->description('Test Response')
                    ->json(
                        Spec\JsonResponse::allOf([
                            Reference::create(Schema::PREFIX . '/test'),
                            [
                                'TEST_OK' => Param\_Integer::create()->minimum(1)->maximum(1)
                            ]
                        ])
                    )
            ];
        }
    }

    class ReferenceFailTestClass implements SpecInterface, MiddlewareInterface {
        public function spec(): Spec {
            return Spec::create()
                        ->httpMethod      (Method\GET)
                        ->path            ('/testing/response')
                        ->response(HTTP\OK, Reference::create(Response::PREFIX . '/test'));
        }

        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oResponse = ResponseBuilder::get($oRequest);
            $oResponse->set('TEST_OK', 2);
            $oResponse->set('REFERENCE_OK', 2);

            return $oHandler->handle(ResponseBuilder::update($oRequest, $oResponse));
        }
    };