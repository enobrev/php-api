<?php
    namespace Enobrev\API\FullSpec\Component;

    use Adbar\Dot;
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

        /** @var string */
        private $sTitle;

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
            $this->sTitle = $sName;
        }

        public function getName(): string {
            return $this->sName;
        }

        public function getSchema() {
            return $this->aSchema;
        }

        public function isOneOf(): string {
            return $this->sType === self::TYPE_ONEOF;
        }

        public function schema($mSchema):self {
            $this->aSchema = $mSchema;
            return $this;
        }

        public function title($sTitle):self {
            $this->sTitle = $sTitle;
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
            $oResponse = new Dot();

            if ($this->sTitle) {
                $oResponse->set('title', $this->sTitle);
            }

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

                $oResponse->set($this->sType, $aResponse);
            } else if ($this->aSchema instanceof OpenApiInterface) {
                $oResponse->merge($this->aSchema->getOpenAPI());
            } else if ($this->aSchema instanceof JsonSchemaInterface) {
                $oResponse->merge($this->aSchema->getJsonSchema());
            } else if (is_array($this->aSchema)) {
                $oResponse->merge(Spec::toJsonSchema($this->aSchema));
            } else {
                $oResponse->merge($this->aSchema);
            }

            return $oResponse->all();
        }
    }