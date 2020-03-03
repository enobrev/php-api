<?php
    namespace Enobrev\API\Param;

    use Enobrev\API\Param;
    use Enobrev\API\ParamTrait;

    class _String extends Param {
        use ParamTrait;

        /** @var string */
        protected $sType = Param::STRING;

        /**
         *
            "date-time": Date representation, as defined by RFC 3339, section 5.6.
            "email": Internet email address, see RFC 5322, section 3.4.1.
            "hostname": Internet host name, see RFC 1034, section 3.1.
            "ipv4": IPv4 address, according to dotted-quad ABNF syntax as defined in RFC 2673, section 3.2.
            "ipv6": IPv6 address, as defined in RFC 2373, section 2.2.
            "uri": A universal resource identifier (URI), according to RFC3986.
         * @param string $sFormat
         * @return _String
         */
        public function format(string $sFormat): self {
            return $this->validation(['format' => $sFormat]);
        }

        public function pattern(string $sFormat): self {
            return $this->validation(['pattern' => $sFormat]);
        }

        public function minLength(int $iMinimum): self {
            return $this->validation(['minLength' => $iMinimum]);
        }

        public function maxLength(int $iMaximum): self {
            return $this->validation(['maxLength' => $iMaximum]);
        }

        /**
         * Heavily inspired by justinrainbow/json-schema, except tries not to coerce nulls into non-nulls
         * @param $mValue
         * @return string
         */
        public function coerce($mValue) {
            if ($this->isNullable()) {
                if ($mValue === null || $mValue === 'null' || $mValue === 0 || $mValue === false || $mValue === '') {
                    return null;
                }
            }

            if (is_numeric($mValue)) {
                return (string) $mValue;
            }

            if ($mValue === true) {
                return 'true';
            }

            if ($mValue === false) {
                return 'false';
            }

            if ($mValue === null) {
                return '';
            }

            if (is_array($mValue) && count($mValue) === 1) {
                return $this->coerce(reset($mValue));
            }

            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return $mValue;
        }
        
        /**
         * @param array $aSchema
         * @return Param\_String
         */
        public static function createFromJsonSchema(array $aSchema) {
            $oParam = self::create();

            if (isset($aSchema['minLength'])) {
                $oParam = $oParam->minLength($aSchema['minLength']);
            }

            if (isset($aSchema['maxLength'])) {
                $oParam = $oParam->maxLength($aSchema['maxLength']);
            }

            if (isset($aSchema['pattern'])) {
                $oParam = $oParam->pattern($aSchema['pattern']);
            }

            if (isset($aSchema['format'])) {
                $oParam = $oParam->format($aSchema['format']);
            }

            return $oParam;
        }
    }