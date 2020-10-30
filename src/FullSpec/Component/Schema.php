<?php
    namespace Enobrev\API\FullSpec\Component;

    use Exception;

    use cebe\openapi\exceptions\TypeErrorException;
    use cebe\openapi\SpecObjectInterface;
    use cebe\openapi\spec\Schema as OpenApi_Schema;

    use Enobrev\API\FullSpec\ComponentInterface;
    use Enobrev\API\OpenApiInterface;
    use Enobrev\API\Spec;
    use Enobrev\Log;

    class Schema implements ComponentInterface, OpenApiInterface {
        private const TYPE_ALLOF = 'allOf';
        private const TYPE_ANYOF = 'anyOf';
        private const TYPE_ONEOF = 'oneOf';

        public const PREFIX = 'schemas';

        /** @var string */
        private $sName;

        /** @var string */
        private $sTitle;

        /** @var OpenApiInterface|array */
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
            }

            $this->sName = implode('/', $aName);
            $this->sTitle = $sName;
        }

        public function getName(): string {
            return $this->sName;
        }

        public function getBodySchema() {
            return $this->aSchema;
        }

        public function isOneOf(): string {
            return $this->sType === self::TYPE_ONEOF;
        }

        public function isAnyOf(): string {
            return $this->sType === self::TYPE_ANYOF;
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

        /**
         * @return SpecObjectInterface
         * @throws TypeErrorException
         */
        public function getSpecObject(): SpecObjectInterface {
            if ($this->sType) {
                $aResponse = [];

                foreach ($this->aSchema as $mSchemaItem) {
                    if ($mSchemaItem instanceof OpenApiInterface) {
                        $aResponse[] = $mSchemaItem->getSpecObject();
                    } else if (is_array($mSchemaItem)) {
                        $aResponse[] = Spec::arrayToSchema($mSchemaItem);
                    } else {
                        Log::d('Component.Schema.getSpecObject.NotSureWhatToDo', $mSchemaItem);
                        //$aResponse[] = $mSchemaItem;
                    }
                }

                $oSpecObject = new OpenApi_Schema([
                    $this->sType => $aResponse
                ]);
            } else if ($this->aSchema instanceof OpenApiInterface) {
                $oSpecObject = $this->aSchema->getSpecObject();
            } else if (is_array($this->aSchema)) {
                $oSpecObject = Spec::arrayToSchema($this->aSchema);
            } else {
                Log::e('Component.Schema.Unhandled', ['schema' => json_encode($this->aSchema)]);
                throw new Exception('Spec.arrayToScheme.Unhandled');
            }

            if ($this->sTitle && $oSpecObject instanceof OpenApi_Schema) {
                $oSpecObject->title = $this->sTitle;
            }

            return $oSpecObject;
        }
    }