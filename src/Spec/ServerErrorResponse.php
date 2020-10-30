<?php
    namespace Enobrev\API\Spec;

    use cebe\openapi\SpecObjectInterface;

    use Enobrev\API\FullSpec;
    use Enobrev\API\FullSpec\Component\Reference;
    use Enobrev\API\HTTP;
    use Enobrev\API\OpenApiInterface;
    use Enobrev\API\Param;

    class ServerErrorResponse implements OpenApiInterface, ErrorResponseInterface {
        use ErrorResponseTrait;

        private int $iCode = HTTP\INTERNAL_SERVER_ERROR;

        private string $sMessage;

        /**
         * @return SpecObjectInterface
         */
        public function getSpecObject(): SpecObjectInterface {
            return JsonResponse::allOf([
                Reference::create(FullSpec::SCHEMA_DEFAULT),
                [
                    '_errors' =>  [
                        'server' => Param\_Object::create()->items([
                            'code'          => Param\_Integer::create()->required()->minimum(100)->maximum(511)->default($this->iCode)->example($this->iCode),
                            'message'       => Param\_String::create()->required()->default($this->sMessage)->example($this->sMessage),
                            'short_stack'   => Param\_Array::create()->items(Param\_Object::create())->description('This will only show up on local or development environments as it contains sensitive information about our backend.  Here you will find a limited version of the exception stack related to the error')
                        ])
                    ]
                ]
            ])->getSpecObject();
        }
    }