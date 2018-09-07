<?php
    namespace Enobrev\API\Spec;

    use Enobrev\API\FullSpec;
    use Enobrev\API\FullSpec\Component\Reference;
    use Enobrev\API\HTTP;
    use Enobrev\API\OpenApiInterface;
    use Enobrev\API\OpenApiResponseSchemaInterface;
    use Enobrev\API\Param;

    class ValidationErrorResponse implements OpenApiInterface, OpenApiResponseSchemaInterface, ErrorResponseInterface {
        use ErrorResponseTrait;

        /** @var number */
        private $iCode = HTTP\BAD_REQUEST;
        
        /** @var string */
        private $sMessage;

        public function getOpenAPI(): array {
            return JsonResponse::allOf([
                Reference::create(FullSpec::SCHEMA_DEFAULT),
                [
                    '_errors' =>  Param\_Object::create()->items([
                        'validation' => Param\_Array::create()->items(
                            Param\_Object::create()->items([
                                "property"      => Param\_String::create()->example("id"),
                                "pointer"       => Param\_String::create()->example("/id/"),
                                "message"       => Param\_String::create()->example("String value found, but an integer is required"),
                                "constraint"    => Param\_String::create()->example([
                                    "name" => "type",
                                    "params" => [
                                        "found" => "string",
                                        "expected" => "an integer"
                                    ]
                                ]),
                                "context"       => Param\_Number::create()->example(1),
                                "value"         => Reference::create(FullSpec::SCHEMA_ANY)
                            ])
                        )
                    ])

                ]
            ])->getOpenAPI();
        }
    }