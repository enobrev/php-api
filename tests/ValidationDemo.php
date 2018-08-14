<?php
    namespace Enobrev\API;

    require __DIR__ . '/../vendor/autoload.php';

    use Enobrev\API\Request;
    use Enobrev\API\Param;
    use Enobrev\API\Validations as Is;
    use Enobrev\Log;

    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\Uri;

    Log::setService('ValidationDemo');

    class Test extends Base {
        public function test() {
            $this->Request->setParams([
                new Param('sha_id', Param::STRING, [new Is\Required(), new Is\ExactLength(40)]),
                new Param('name',   Param::STRING, [new Is\Required, new Is\Length(3, 30)]),
                new Param('email',  Param::STRING, [new Is\Required]),
                new Param('age',    Param::INT,    [new Is\AtLeast(18), new Is\LessThan(150)])
            ]);

            $this->Response->document([
                'description' => 'Whatever',
                'response' => [
                    'users' => new ResponseObject('users', [
                        'id' => new ResponseString,
                        'name' => new ResponseString,
                        'age'  => new ResponseInt
                    ])
                ]
            ]);

            $this->Response->add('name', $this->Request->Params['name']);
        }
    }

    /** @var ServerRequest $oServerRequest */
    $oServerRequest = new ServerRequest;
    $oServerRequest = $oServerRequest->withMethod('GET');
    $oServerRequest = $oServerRequest->withUri(new Uri('http://example.com/test/test'));
    $oServerRequest = $oServerRequest->withQueryParams([
        'name'   => 'mark',
        'sha_id' => 'abcdefghijklmnopqrstuvwxyz01234567890123',
        'email'  => 'enobrev@gmail.com',
        'age'    => '45'
    ]);

    $oRequest = new Request($oServerRequest);
    $oRequest->addParams(
        new Param('sha_id', Param::STRING, [new Is\Required(), new Is\ExactLength(40)]),
        new Param('name',   Param::STRING, [new Is\Required, new Is\Length(3, 30)]),
        new Param('email',  Param::STRING, [new Is\Required]),
        new Param('age',    Param::INT,    [new Is\AtLeast(18), new Is\LessThan(150)])
    );

    \Enobrev\dbg($oRequest->documentParams());
    \Enobrev\dbg($oRequest->validateParams());

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