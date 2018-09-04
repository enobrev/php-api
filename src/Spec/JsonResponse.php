<?php
    namespace Enobrev\API\Spec;

    use Enobrev\API\FullSpec;
    use Enobrev\API\OpenApiInterface;
    use Enobrev\API\OpenApiResponseSchemaInterface;
    use Enobrev\API\Spec;
    use function Enobrev\dbg;

    class JsonResponse implements OpenApiInterface, OpenApiResponseSchemaInterface {
        const TYPE_ALLOF = 'allOf';
        const TYPE_ANYOF = 'anyOf';
        const TYPE_ONEOF = 'oneOf';

        /** @var OpenApiInterface|OpenApiInterface[] */
        private $mSchema;

        /** @var string */
        private $sType;

        public static function create():self {
            return new self();
        }

        public static function schema($mSchema):self {
            $oResponse = new self();
            $oResponse->mSchema = $mSchema;
            return $oResponse;
        }

        public static function allOf(array $aSchemas):self {
            $oResponse = new self();
            $oResponse->mSchema = $aSchemas;
            $oResponse->sType   = self::TYPE_ALLOF;
            return $oResponse;
        }

        public static function anyOf(array $aSchemas):self {
            $oResponse = new self();
            $oResponse->mSchema = $aSchemas;
            $oResponse->sType   = self::TYPE_ANYOF;
            return $oResponse;
        }

        public static function oneOf(array $aSchemas):self {
            $oResponse = new self();
            $oResponse->mSchema = $aSchemas;
            $oResponse->sType   = self::TYPE_ONEOF;
            return $oResponse;
        }

        public function getOpenAPI(): array {
            if (!$this->mSchema) {
                return RefResponse::create(FullSpec::RESPONSE_DEFAULT)->getOpenAPI();
            }

            if ($this->sType) {
                $aResponse = [];

                foreach($this->mSchema as $mSchemaItem) {
                    if ($mSchemaItem instanceof OpenApiInterface) {
                        $aResponse[] = $mSchemaItem->getOpenAPI();
                    } else if (is_array($mSchemaItem)) {
                        $aResponse[] = Spec::toJsonSchema($mSchemaItem);
                    } else {
                        $aResponse[] = $mSchemaItem;
                    }
                }

                return [
                    $this->sType => $aResponse
                ];
            } else if ($this->mSchema instanceof OpenApiInterface) {
                return $this->mSchema->getOpenAPI();
            } else if (is_array($this->mSchema)) {
                return Spec::toJsonSchema($this->mSchema);
            }
        }
    }