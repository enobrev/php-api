<?php
    namespace Enobrev\API\Spec;

    use cebe\openapi\SpecObjectInterface;

    use Enobrev\API\FullSpec;
    use Enobrev\API\FullSpec\Component\Reference;
    use Enobrev\API\OpenApiInterface;
    use Enobrev\API\Param;
    use Enobrev\API\HTTP;

    class ProcessErrorResponse implements OpenApiInterface, ErrorResponseInterface {
        use ErrorResponseTrait;

        private int $iCode = HTTP\UNPROCESSABLE_ENTITY;

        private string $sMessage;

        public function getSpecObject(): SpecObjectInterface {
            return JsonResponse::allOf([
                Reference::create(FullSpec::SCHEMA_DEFAULT),
                [
                    '_errors' =>  [
                        'process' => Param\_Object::create()->items([
                            'code'    => Param\_Integer::create()->minimum(100)->maximum(511)->default($this->iCode)->example($this->iCode),
                            'message' => Param\_String::create()->default($this->sMessage)->example($this->sMessage)
                        ])
                    ]
                ]
            ])->getSpecObject();
        }
    }