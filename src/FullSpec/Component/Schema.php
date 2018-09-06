<?php
    namespace Enobrev\API\FullSpec\Component;

    use Enobrev\API\FullSpec\ComponentInterface;
    use Enobrev\API\JsonSchemaInterface;
    use Enobrev\API\OpenApiInterface;
    use Enobrev\API\Param;
    use Enobrev\API\Spec;
    use function Enobrev\dbg;

    class Schema implements ComponentInterface, OpenApiInterface {
        const TYPE_ALLOF = 'allOf';
        const TYPE_ANYOF = 'anyOf';
        const TYPE_ONEOF = 'oneOf';

        const PREFIX = 'schemas';

        /** @var string */
        private $sName;

        /** @var OpenApiInterface|JsonSchemaInterface|array */
        private $aSchema;

        /** @var string */
        private $sType;

        public static function create(string $sName) {
            return new self($sName);
        }

        public function __construct($sName) {
            $aName = explode('/', $sName);
            if (count($aName) === 1) {
                array_unshift($aName, self::PREFIX);
            } else if ($aName[0] !== self::PREFIX) {
                array_unshift($aName, self::PREFIX);
            };

            $this->sName = implode('/', $aName);
        }

        public function getName(): string {
            return $this->sName;
        }

        public function schema($mSchema):self {
            $this->aSchema = $mSchema;
            return $this;
        }

        public function allOf(array $aSchemas):self {
            $this->aSchema = $aSchemas;
            $this->sType   = self::TYPE_ALLOF;
            return $this;
        }

        public function anyOf(array $aSchemas):self {
            $this->aSchema = $aSchemas;
            $this->sType   = self::TYPE_ANYOF;
            return $this;
        }

        public function oneOf(array $aSchemas):self {
            $this->aSchema = $aSchemas;
            $this->sType   = self::TYPE_ONEOF;
            return $this;
        }


        public function getOpenAPI(): array {
            if ($this->sType) {
                $aResponse = [];

                foreach ($this->aSchema as $mSchemaItem) {
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
            } else if ($this->aSchema instanceof OpenApiInterface) {
                return $this->aSchema->getOpenAPI();
            } else if ($this->aSchema instanceof JsonSchemaInterface) {
                return $this->aSchema->getJsonSchema();
            } else if (is_array($this->aSchema)) {
                return Spec::toJsonSchema($this->aSchema);
            } else {
                return $this->aSchema;
            }
        }
    }