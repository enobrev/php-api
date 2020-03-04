<?php
    namespace Enobrev\API\Param;

    use cebe\openapi\spec\Schema;
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
         * @param Schema $oSchema
         * @return self
         */
        public static function createFromSchema(Schema $oSchema): self {
            $oParam = self::create();

            if ($oSchema->minLength) {
                $oParam = $oParam->minLength($oSchema->minLength);
            }

            if ($oSchema->maxLength) {
                $oParam = $oParam->maxLength($oSchema->maxLength);
            }

            if ($oSchema->pattern) {
                $oParam = $oParam->pattern($oSchema->pattern);
            }

            if ($oSchema->format) {
                $oParam = $oParam->format($oSchema->format);
            }

            return $oParam;
        }
    }