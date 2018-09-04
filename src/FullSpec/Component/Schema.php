<?php
    namespace Enobrev\API\FullSpec\Component;

    use Enobrev\API\FullSpec\ComponentInterface;
    use Enobrev\API\JsonSchemaInterface;
    use Enobrev\API\OpenApiInterface;
    use function Enobrev\dbg;

    class Schema implements ComponentInterface, OpenApiInterface {
        const PREFIX = 'schemas';

        /** @var string */
        private $sName;

        /** @var OpenApiInterface|JsonSchemaInterface|array */
        private $aSchema;

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

        public function getOpenAPI(): array {
            if ($this->aSchema instanceof OpenApiInterface) {
                return $this->aSchema->getOpenAPI();
            } else if ($this->aSchema instanceof JsonSchemaInterface) {
                return $this->aSchema->getJsonSchema();
            } else if (is_array($this->aSchema)) {
                $aResponse = [];
                foreach($this->aSchema as $sName => $mSchema) {
                    if (is_scalar($mSchema)) {
                        $aResponse[$sName] = $mSchema;
                    } else {
                        $aResponse[$sName] = self::create($sName)->schema($mSchema)->getOpenAPI();
                    }
                }
                return $aResponse;
            } else {
                return $this->aSchema;
            }
        }
    }