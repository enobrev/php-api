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

    class NestedJsonSchemaFailTest extends TestCase {
        /** @var array  */
        private $aPipeline = [];

        public function setUp(): void {
            parent::setUp();

            $oFullSpec = FullSpec::getInstance();
            $oFullSpec->addSpecFromInstance(new NestedJsonSchemaFailTestClass());

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

        public function testOk(): void {
            $this->expectException(ValidationException::class);

            try {
                $this->getResponse();
            } catch (ValidationException $e) {
                $aContext = $e->getContext();

                $this->assertIsArray($aContext);
                $this->assertCount(1, $aContext);
                $this->assertEquals('test1.testerton.x', $aContext[0]['property']);
                $this->assertEquals('maximum',           $aContext[0]['constraint']);

                throw $e;
            }
        }
    }

    class NestedJsonSchemaFailTestClass implements SpecInterface, MiddlewareInterface {
        public function spec(): Spec {
            return Spec::create()
                        ->httpMethod      (Method\GET)
                        ->path            ('/testing/response')
                        ->response(HTTP\OK,
                            Spec\JsonResponse::schema([
                                'test1.testerton.x' => Param\_Integer::create()->minimum(1)->maximum(1),
                                'test2.testerosa' => Param\_String::create()->enum(['test']),
                                'test3' => Param\_Array::create()->items(Param\_String::create())
                            ])
                        );
        }

        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oResponse = ResponseBuilder::get($oRequest);
            $oResponse->set('test1.testerton.x', 2);
            $oResponse->set('test2.testerosa', 'test');
            $oResponse->set('test3', ['a', 'b', 'c']);

            return $oHandler->handle(ResponseBuilder::update($oRequest, $oResponse));
        }
    };