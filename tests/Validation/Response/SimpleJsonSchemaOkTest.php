<?php /** @noinspection PhpUnhandledExceptionInspection */

    namespace Enobrev\Test\Validation\Response;

    require __DIR__ . '/../../../vendor/autoload.php';

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
    use Enobrev\API\Middleware\Request\AttributeSpec;
    use Enobrev\API\Middleware\Request\CoerceParams;
    use Enobrev\API\Middleware\RequestHandler;
    use Enobrev\API\Middleware\Response\ValidateResponse;
    use Enobrev\API\Middleware\ResponseBuilderDone;
    use Enobrev\API\Middleware\ResponseBuilder;
    use Enobrev\API\Param;
    use Enobrev\API\Spec;
    use Enobrev\API\SpecInterface;

    class SimpleJsonSchemaOkTest extends TestCase {
        /** @var array  */
        private $aPipeline = [];

        public function setUp(): void {
            parent::setUp();

            $oFullSpec = FullSpec::getInstance();
            $oFullSpec->addSpecFromInstance(new SimpleJsonSchemaOkTestClass());

            $this->aPipeline = [
                new ResponseBuilder(),
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
            $oResponse = $this->getResponse();
            $sResponse = $oResponse->getBody()->getContents();
            $aResponse = json_decode($sResponse, true);

            $this->assertEquals(200, $oResponse->getStatusCode());
            $this->assertEquals(1, $aResponse['TEST_OK']);
        }
    }

    class SimpleJsonSchemaOkTestClass implements SpecInterface, MiddlewareInterface {
        public function spec(): Spec {
            return Spec::create()
                        ->httpMethod      (Method\GET)
                        ->path            ('/testing/response')
                        ->response(HTTP\OK,
                            Spec\JsonResponse::schema([
                                'TEST_OK' => Param\_Integer::create()->minimum(1)->maximum(1)
                            ])
                        );
        }

        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oResponse = ResponseBuilder::get($oRequest);
            $oResponse->set('TEST_OK', 1);

            return $oHandler->handle(ResponseBuilder::update($oRequest, $oResponse));
        }
    };