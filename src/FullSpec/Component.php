<?php
    namespace Enobrev\API\FullSpec;


    use Adbar\Dot;
    use Enobrev\API\FullSpec;
    use Enobrev\API\Param;
    use function Enobrev\array_not_associative;

    class Component {
        const SCHEMA_DEFAULT = self::TYPE_SCHEMA . '/_default';

        const MIME_JSON      = 'application/json';
        const MIME_FORM      = 'multipart/form-data';

        const TYPE_SCHEMA    = 'schemas';
        const TYPE_RESPONSE  = 'responses';
        const TYPE_PARAMETER = 'parameters';
        const TYPE_EXAMPLE   = 'examples';
        const TYPE_REQUEST   = 'requestBodies';
        const TYPE_HEADER    = 'headers';
        const TYPE_SECURITY  = 'securitySchemes';
        const TYPE_LINK      = 'links';
        const TYPE_CALLBACK  = 'callbacks';

        const TYPE_REFERENCE = 'references'; // Special Type

        const TYPES = [
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
            if ($sType == self::TYPE_REFERENCE) {
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

        public function getName() {
            return $this->sName;
        }

        public function getDescription() {
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
                } else {
                    if ($aValue instanceof self
                    ||  $aValue instanceof Param) {
                        $aOutput[$sKey] = $aValue;
                    } else if (is_array($aValue)) {
                        if (isset($aValue['type'])) { // Likely a Param Array
                            $aOutput[$sKey] = [
                                'type'       => 'object',
                                "additionalProperties" => false,
                                'properties' => $aValue
                            ];
                        } else {
                            $aOutput[$sKey] = self::arrayToOAObject($aValue);
                        }
                    }
                }
            }

            return  [
                'type'       => 'object',
                "additionalProperties" => false,
                'properties' => $aOutput
            ];
        }

        /**
         * @return array|mixed
         */
        public function getOpenAPI() {

            switch($this->sType) {
                case self::TYPE_REFERENCE:
                    return ['$ref' => "#/components/{$this->sName}"];
                    break;

                case self::TYPE_SCHEMA:
                    if (is_array($this->mSchema)) {
                        $aResponse = [];
                        foreach($this->mSchema as $sName => $mSubSchema) {
                            if ($mSubSchema instanceof Param) {
                                $aResponse[$sName] = $mSubSchema->getJsonSchema();
                            } else if ($mSubSchema instanceof self) {
                                $aResponse[$sName] = $mSubSchema->getOpenAPI();
                            } else if (is_array($mSubSchema)) {
                                $aResponse[$sName] = self::schema(self::TYPE_SCHEMA . '/' . $sName, $mSubSchema)->getOpenAPI();
                            } else {
                                $aResponse[$sName] = $mSubSchema;
                            }
                        }

                        return $aResponse;
                    }

                    return $this->mSchema;
                    break;
                    
                case self::TYPE_REQUEST:
                    $oResponse = new Dot([
                        'description' => $this->sDescription,
                        'content' => []
                    ]);

                    foreach($this->mSchema as $sMimeType => $mSubSchema) {
                        switch(true) {
                            case $mSubSchema instanceof self:
                                $oResponse->set("content.$sMimeType.schema", $mSubSchema->getOpenAPI());
                                break;
                        }
                    }

                    return $oResponse->all();
                    break;

                case self::TYPE_RESPONSE:
                    if (!$this->mSchema) {
                        // If No schema is given, then simply apply the name and description to the default
                        return self::response($this->sName, $this->sDescription, [Component::MIME_JSON => Component::ref(FullSpec::SCHEMA_DEFAULT)])->getOpenAPI();
                    }
                    
                    $oResponse = new Dot([
                        'description' => $this->sDescription,
                        'content' => []
                    ]);

                    foreach($this->mSchema as $sMimeType => $mSubSchema) {
                        switch(true) {
                            case $mSubSchema instanceof self:
                                $oResponse->set("content.$sMimeType.schema", $mSubSchema->getOpenAPI());
                                break;

                            case is_array($mSubSchema) && count($mSubSchema) > 1:
                                $aSubSchemas = [];
                                foreach($mSubSchema as $mSubSub) {
                                    if ($mSubSub instanceof self) {
                                        $aSubSchemas[] = $mSubSub->getOpenAPI();
                                    } else {
                                        $aSubSchemas[] = $mSubSub;
                                    }
                                }
                                $oResponse->set("content.$sMimeType.schema.allOf", $aSubSchemas);
                                break;

                            case is_array($mSubSchema) && count($mSubSchema) == 1:
                                if (array_not_associative($mSubSchema) && $mSubSchema[0] instanceof self) {
                                    $oResponse->set("content.$sMimeType.schema", $mSubSchema[0]->getOpenAPI());
                                } else {
                                    $oResponse->set("content.$sMimeType.schema", $mSubSchema);
                                }
                                break;
                        }
                    }

                    return $oResponse->all();
                    break;
            }
        }
    }