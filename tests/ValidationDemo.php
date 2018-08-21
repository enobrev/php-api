<?php
    namespace Enobrev\API;

    require __DIR__ . '/../vendor/autoload.php';

    use Enobrev\API\Exception\DocumentationException;
    use Enobrev\API\Exception\InvalidRequest;
    use function Enobrev\dbg;
    use Enobrev\Log;

    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\Uri;
    use Enobrev\API\Method;
    use Enobrev\API\HTTP;

    use Enobrev\API\Mock\Table;

    Log::setService('ValidationDemo');

    class TestSpec extends Base {
        public function test() {
            $this->Response->Spec
                ->summary('Test')
                ->description('Testing')
                ->scopes(['www'])
                ->method([Method\GET])
                ->inParams([
                    new Param('sha_id', Param::STRING | Param::REQUIRED, ["minLength" => 40, "maxLength" => 40], "Client Generated Sha1 Hash"),
                    new Param('name',   Param::STRING, ["minLength" => 3, "maxLength" => 30], "The Person's full name"),
                    new Param('email',  Param::STRING),
                    new Param('age',    Param::INTEGER,["minimum" => 18, "maximum" => 150, "exclusiveMaximum" => true])
                ])
                ->outTable('User', new Table\User)
                ->outSchema('User', [
                    new Param('is_authed', Param::BOOLEAN, ['default' => false])
                ]);

            try {
                $this->Response->Spec->ready();
            } catch(DocumentationException | InvalidRequest $e) {
                return;
            }

            dbg('TEST!');
        }

        public function defineOutputTypes() {
            $this->Response->defineOutputType("Users", [
                "x-patternProperties" => [
                    "^[a-fA-F0-9]+$" => ['$ref' => "User"]
                ]
            ]);
        }
    }

    /** @var ServerRequest $oServerRequest */
    $oServerRequest = new ServerRequest;
    $oServerRequest = $oServerRequest->withMethod('GET');
    $oServerRequest = $oServerRequest->withUri(new Uri('http://example.com/test/test'));
//    $oServerRequest = $oServerRequest->withAddedHeader('X-Welcome-Docs', 1);
    $oServerRequest = $oServerRequest->withQueryParams([
        'name'   => 'mark',
//        'sha_id' => 'abcdefghijklmnopqrstuvwxyz0123456789012',
        'email'  => 'enobrev@gmail.com',
        'age'    => 45
    ]);

    $oRequest = new Request($oServerRequest);

    DataMap::setDataFile(__DIR__ . '/Mock/DataMap.json');
    Response::init('example.com');
    Route::addEndpointRoute('testspec/test',TestSpec::class,'test');

    $oTest = new TestSpec($oRequest);
    $oTest->test();
    dbg(json_encode($oTest->Response->getOutput(), JSON_PRETTY_PRINT));

    /*
     *
    $oRequest->addParams([
        'sha_id' => ["[>40<]", "required"],
        'name'   => ["[>3", "30<]", "required"],
        'email'  => ["required"],
        'age'    => [">=18", "<150"]
    ]);

     */

    // Define Params in each Method

    // Add means of Table => Params generation

    // Standardize Errors returned from Validation

    // Standardize non-validation Errors

    // Allow Method to be called in "Doc" mode, which does not call actual methods but instead merely returns Request Params and Response Params
