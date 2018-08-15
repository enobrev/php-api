<?php
    namespace Enobrev\API;

    require __DIR__ . '/../vendor/autoload.php';

    use Enobrev\API\Exception\DocumentationException;
    use Enobrev\API\Exception\InvalidRequest;
    use function Enobrev\dbg;
    use Enobrev\Log;

    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\Uri;


    Log::setService('ValidationDemo');


    class TestFullSchema extends Base {
        public function test() {
            $this->Response->setSchema(null, [
                "type" => "object",
                "additionalProperties" => false,
                "properties" => [
                    "request" => [
                        "type" => "object",
                        "additionalProperties" => false,
                        "properties" => [
                            "sha_id" => ["type" => "string", "minLength" => 40, "maxLength" => 40, "description" => "Client Generated Sha1 Hash"],
                            "name"   => ["type" => "string", "minLength" => 3, "maxLength" => 30, "description" => "The Person's full name"],
                            "email"  => ["type" => "string"],
                            "age"    => ["type" => "integer", "minimum" => 18, "maximum" => 150, "exclusiveMaximum" => true]
                        ],
                        "required" => ["sha_id"]
                    ],
                    "response" => [
                        "type" => "object",
                        "additionalProperties" => false,
                        "properties" => [
                            "users" => ["\$ref" => "#/definitions/users"]
                        ]
                    ]
                ],
                "definitions" => [
                    "users" => [
                        "patternProperties" => [
                            "^[a-fA-F0-9]+$" => ["\$ref" => "#/definitions/user"]
                        ],
                        "additionalProperties" => false
                    ],
                    "user" => [
                        "properties" => [
                            "id" => ["type" => "string"],
                        ],
                        "additionalProperties" => false
                    ]
                ]
            ]);

            try {
                $this->Response->validateRequest();
            } catch(DocumentationException | InvalidRequest $e) {
                return;
            }

            // $this->Response->add('name', $this->Request->Params['name']);
            dbg('TEST!');
        }
    }

    class TestPartialSchema extends Base {
        public function test() {
            $this->Response->setSchema('properties.request.properties', [
                "sha_id" => ["type" => "string", "minLength" => 40, "maxLength" => 40, "description" => "Client Generated Sha1 Hash"],
                "name"   => ["type" => "string", "minLength" => 3, "maxLength" => 30, "description" => "The Person's full name"],
                "email"  => ["type" => "string"],
                "age"    => ["type" => "integer", "minimum" => 18, "maximum" => 150, "exclusiveMaximum" => true]
            ]);

            $this->Response->setSchema('properties.request.required', ["sha_id"]);

            $this->schema();

            try {
                $this->Response->validateRequest();
            } catch(DocumentationException | InvalidRequest $e) {
                return;
            }

            dbg('TEST!');
        }

        public function schema() {
            $this->Response->setSchema('properties.response.properties', [
                "users" => ["\$ref" => "#/definitions/users"]
            ]);

            $this->Response->setSchema('definitions', [
                "users" => [
                    "patternProperties" => [
                        "^[a-fA-F0-9]+$" => ["\$ref" => "#/definitions/user"]
                    ],
                    "additionalProperties" => false
                ],
                "user" => [
                    "properties" => [
                        "id" => ["type" => "string"],
                    ],
                    "additionalProperties" => false
                ]
            ]);
        }
    }

    /** @var ServerRequest $oServerRequest */
    $oServerRequest = new ServerRequest;
    $oServerRequest = $oServerRequest->withMethod('GET');
    $oServerRequest = $oServerRequest->withUri(new Uri('http://example.com/test/test'));
    $oServerRequest = $oServerRequest->withQueryParams([
        'document' => true,
        'name'   => 'mark',
        'sha_id' => 'abcdefghijklmnopqrstuvwxyz01234567890123',
        'email'  => 'enobrev@gmail.com',
        'age'    => 45
    ]);

    $oRequest = new Request($oServerRequest);

    Response::init('example.com');

    $oTest = new TestFullSchema($oRequest);
    $oTest->test();
    $oTest->Response->validateResponse();
    dbg(json_encode($oTest->Response->getOutput(), JSON_PRETTY_PRINT));

    $oTest = new TestPartialSchema($oRequest);
    $oTest->test();
    $oTest->Response->validateResponse();
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
