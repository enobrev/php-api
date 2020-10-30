<?php
    namespace Enobrev\API\Spec;

    use cebe\openapi\exceptions\TypeErrorException;
    use cebe\openapi\SpecObjectInterface;
    use Enobrev\API\FullSpec;
    use Enobrev\API\FullSpec\Component\Reference;
    use Enobrev\API\OpenApiInterface;
    use Enobrev\API\Param;
    use Enobrev\API\HTTP;

    class AuthenticationErrorResponse implements OpenApiInterface, ErrorResponseInterface {
        use ErrorResponseTrait;

        private int $iCode = HTTP\UNAUTHORIZED;

        private string $sMessage;

        /**
         * @return SpecObjectInterface
         * @throws TypeErrorException
         */
        public function getSpecObject(): SpecObjectInterface {
            return JsonResponse::allOf([
                Reference::create(FullSpec::SCHEMA_DEFAULT),
                [
                    '_errors' =>  [
                        'authentication' => Param\_Object::create()->items([
                            'code'    => Param\_Integer::create()->minimum(401)->maximum(403)->default($this->iCode)->example($this->iCode),
                            'message' => Param\_String::create()->default($this->sMessage)->example($this->sMessage)
                        ])
                    ]
                ]
            ])->getSpecObject();
        }
    }