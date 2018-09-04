<?php
    namespace Enobrev\API\Spec;

    use Enobrev\API\FullSpec;
    use Enobrev\API\FullSpec\Component\Reference;
    use Enobrev\API\OpenApiInterface;
    use Enobrev\API\OpenApiResponseSchemaInterface;
    use Enobrev\API\Param;
    use Enobrev\API\HTTP;

    class ProcessErrorResponse implements OpenApiInterface, OpenApiResponseSchemaInterface {
        /** @var number */
        private $iCode = HTTP\UNPROCESSABLE_ENTITY;
        
        /** @var string */
        private $sMessage;
        public static function create():self {
            return new self();
        }

        public function code(int $iCode):self {
            $this->iCode = $iCode;
            return $this;
        }

        public function message($sMessage):self {
            $this->sMessage = $sMessage;
            return $this;
        }

        public function getOpenAPI(): array {
            return JsonResponse::allOf([
                Reference::create(FullSpec::SCHEMA_DEFAULT),
                [
                    '_errors' => [
                        'process' => [
                            'code'    => Param\_Integer::create()->minimum(100)->maximum(511)->default($this->iCode)->example($this->iCode),
                            'message' => Param\_String::create()->default($this->sMessage)->example($this->sMessage)
                        ]
                    ]
                ]
            ])->getOpenAPI();
        }
    }