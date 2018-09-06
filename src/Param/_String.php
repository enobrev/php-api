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

        public function getJsonSchema(): array {
            return parent::getJsonSchema();
        }

        public function getJsonSchemaForOpenAPI(): array {
            return parent::getJsonSchemaForOpenAPI();
        }
    }