<?php
    namespace Enobrev\API\FullSpec;


    use Adbar\Dot;
    use Enobrev\API\FullSpec;
    use Enobrev\API\Param;
    use function Enobrev\array_not_associative;

    class Component {
        private const SCHEMA_DEFAULT = self::TYPE_SCHEMA . '/_default';

        private const MIME_JSON      = 'application/json';
        private const MIME_FORM      = 'multipart/form-data';

        private const TYPE_SCHEMA    = 'schemas';
        private const TYPE_RESPONSE  = 'responses';
        private const TYPE_PARAMETER = 'parameters';
        private const TYPE_EXAMPLE   = 'examples';
        private const TYPE_REQUEST   = 'requestBodies';
        private const TYPE_HEADER    = 'headers';
        private const TYPE_SECURITY  = 'securitySchemes';
        private const TYPE_LINK      = 'links';
        private const TYPE_CALLBACK  = 'callbacks';

        private const TYPE_REFERENCE = 'references'; // Special Type

        private const TYPES = [
            self::TYPE_SCHEMA,   self::TYPE_RESPONSE, self::TYPE_PARAMETER,
            self::TYPE_EXAMPLE,  self::TYPE_REQUEST,  self::TYPE_HEADER,
            self::TYPE_SECURITY, self::TYPE_LINK,     self::TYPE_CALLBACK,
            self::TYPE_REFERENCE
        ];
        
        /** @var string */
        private $sName;
        
        /** @var string */
        private $sType;

        /** @var string */
        private $sDescription;

        /** @var mixed */
        private $mSchema;

        private function __construct(string $sType, string $sName) {
            $this->sType = $sType;
            $this->sName = self::ensureNameHasType($sType, $sName);
        }

        private static function ensureNameHasType(string $sType, string $sName):string {
            if ($sType === self::TYPE_REFERENCE) {
                return $sName;
            }

            $aName = explode('/', $sName);
            if (count($aName) === 1) {
                array_unshift($aName, $sType);
            } else if ($aName[0] !== $sType) {
                array_unshift($aName, $sType);
            }

            return implode('/', $aName);
        }

        public function getName(): string {
            return $this->sName;
        }

        public function getDescription(): string {
            return $this->sDescription;
        }

        public static function schema(string $sName, Component\Schema $oSchema):self {
            $oComponent = new self(self::TYPE_SCHEMA, $sName);
            $oComponent->mSchema = $oSchema;
            return $oComponent;
        }

        public static function request(string $sName, string $sDescription, Component\Request $oRequest):self {
            $oComponent = new self(self::TYPE_REQUEST, $sName);
            $oComponent->sDescription = $sDescription;
            $oComponent->mSchema = $oRequest;
            return $oComponent;
        }

        /**
         * Defines a Response Schema or Reference.  If no Schema is given, it will use the schema for the default response as defined in FullSpecComponent
         *
         * @param string                  $sName
         * @param string                  $sDescription
         * @param Component\Response|null $oResponse
         *
         * @return Component
         */
        public static function response(string $sName, string $sDescription, ?Component\Response $oResponse = null):self {
            $oComponent = new self(self::TYPE_RESPONSE, $sName);
            $oComponent->sDescription = $sDescription;
            if ($oResponse) {
                $oComponent->mSchema = $oResponse;
            }
            return $oComponent;
        }

        public static function ref(string $sName):self {
            return new self(self::TYPE_REFERENCE, $sName);
        }

        /**
         * Converts a simple array (including dot-delimited keys) into an OpenAPI Object
         * @param array $aArray
         * @return array
         */
        public static function arrayToOAObject(array $aArray): array {
            $aOutput = [];

            foreach($aArray as $sKey => $aValue) {
                if (strpos($sKey, '.') !== false) {
                    $oConvert = new Dot();
                    $oConvert->set($sKey, $aValue);
                    foreach($oConvert->all() as $sConvertedKey => $aConvertedValue) {
                        $aOutput[$sConvertedKey] =  self::arrayToOAObject($aConvertedValue);
                    }
                } else if ($aValue instanceof self
                       ||  $aValue instanceof Param) {
                    $aOutput[$sKey] = $aValue;
                } else if (is_array($aValue)) {
                    if (isset($aValue['type'])) { // Likely a Param Array
                        $aOutput[$sKey] = [
                            'type'                 => 'object',
                            'additionalProperties' => false,
                            'properties'           => $aValue
                        ];
                    } else {
                        $aOutput[$sKey] = self::arrayToOAObject($aValue);
                    }
                }
            }

            return  [
                'type'                 => 'object',
                'additionalProperties' => false,
                'properties'           => $aOutput
            ];
        }
    }