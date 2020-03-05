<?php
    namespace Enobrev\API\Spec;

    use cebe\openapi\spec\Schema;
    use cebe\openapi\spec\Reference;
    use cebe\openapi\SpecObjectInterface;

    use Enobrev\API\FullSpec;
    use Enobrev\API\OpenApiInterface;
    use Enobrev\API\Spec;
    use Enobrev\Log;

    class JsonResponse implements OpenApiInterface {
        private const TYPE_ALLOF = 'allOf';
        private const TYPE_ANYOF = 'anyOf';
        private const TYPE_ONEOF = 'oneOf';

        /** @var OpenApiInterface|OpenApiInterface[] */
        private $mSchema;

        /** @var string */
        private $sType;

        /** @var string */
        private $sTitle;

        public function __construct(?string $sTitle = null) {
            $this->sTitle = $sTitle;
        }

        public static function create(?string $sTitle = null):self {
            return new self($sTitle);
        }

        public static function schema($mSchema, ?string $sTitle = null):self {
            $oResponse = new self($sTitle);
            $oResponse->mSchema = $mSchema;
            return $oResponse;
        }

        public static function allOf(array $aSchemas, ?string $sTitle = null):self {
            $oResponse = new self($sTitle);
            $oResponse->mSchema = $aSchemas;
            $oResponse->sType   = self::TYPE_ALLOF;
            return $oResponse;
        }

        public static function anyOf(array $aSchemas, ?string $sTitle = null):self {
            $oResponse = new self($sTitle);
            $oResponse->mSchema = $aSchemas;
            $oResponse->sType   = self::TYPE_ANYOF;
            return $oResponse;
        }

        public static function oneOf(array $aSchemas, ?string $sTitle = null):self {
            $oResponse = new self($sTitle);
            $oResponse->mSchema = $aSchemas;
            $oResponse->sType   = self::TYPE_ONEOF;
            return $oResponse;
        }

        /**
         * @return SpecObjectInterface
         * @throws \cebe\openapi\exceptions\TypeErrorException
         */
        public function getSpecObject(): SpecObjectInterface {
            if (!$this->mSchema) {
                return new Reference(['$ref' => FullSpec::RESPONSE_DEFAULT]);
            }

            if ($this->sType) {
                $aResponse = [];

                foreach($this->mSchema as $mSchemaItem) {
                    if ($mSchemaItem instanceof SpecObjectInterface) {
                        $aResponse[] = $mSchemaItem;
                    } else if ($mSchemaItem instanceof OpenApiInterface) {
                        $aResponse[] = $mSchemaItem->getSpecObject();
                    } else if (is_array($mSchemaItem)) {
                        $aResponse[] = Spec::arrayToSchema($mSchemaItem);
                    } else {
                        Log::e('JsonResponse.getSpecObject.Unhandled', ['schema' => json_encode($mSchemaItem)]);
                        throw new \Exception('JsonResponse.getSpecObject.Unhandled');
                        //$aResponse[] = $mSchemaItem;
                    }
                }

                $aReturn = [
                    $this->sType => $aResponse
                ];

                if ($this->sTitle) {
                    $aReturn['title'] = $this->sTitle;
                }

                return new Schema($aReturn);
            }

            if ($this->mSchema instanceof OpenApiInterface) {
                return $this->mSchema->getSpecObject();
            }

            if (is_array($this->mSchema)) {
                return Spec::arrayToSchema($this->mSchema);
            }

            throw new \Exception('No Schema to Return');
        }
    }