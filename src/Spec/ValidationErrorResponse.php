<?php
    namespace Enobrev\API\Spec;

    use cebe\openapi\SpecObjectInterface;

    use Enobrev\API\FullSpec;
    use Enobrev\API\FullSpec\Component\Reference;
    use Enobrev\API\HTTP;
    use Enobrev\API\OpenApiInterface;
    use Enobrev\API\Param;

    class ValidationErrorResponse implements OpenApiInterface, ErrorResponseInterface {
        use ErrorResponseTrait;

        private int $iCode = HTTP\BAD_REQUEST;

        private string $sMessage;

        /**
         * @return SpecObjectInterface
         */
        public function getSpecObject(): SpecObjectInterface {
            return JsonResponse::allOf([
                Reference::create(FullSpec::SCHEMA_DEFAULT),
                [
                    '_errors' =>  [
                        'validation' => Param\_Array::create()->items(
                            Param\_Object::create()->items([
                                'property'   => Param\_String::create()->example('id'),
                                'pointer'    => Param\_String::create()->example('/id/'),
                                'message'    => Param\_String::create()->example('String value found, but an integer is required'),
                                'constraint' => Param\_String::create()->addExample('constraint', [
                                    'name'   => 'type',
                                    'params' => [
                                        'found'    => 'string',
                                        'expected' => 'an integer'
                                    ]
                                ]),
                               'context'    => Param\_Number::create()->example(1),
                               'value'      => Reference::create(FullSpec::SCHEMA_ANY)
                            ])
                        )->getSchema()
                    ]
                ]
            ])->getSpecObject();
        }
    }