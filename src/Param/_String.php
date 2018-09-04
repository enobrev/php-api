<?php
    namespace Enobrev\API\Param;

    use Enobrev\API\Param;

    class _String extends Param {
        public static function create(): self {
            return new self();
        }

        public function __construct() {
            parent::__construct(Param::STRING);
        }

        public function getJsonSchema(): array {
            return parent::getJsonSchema();
        }

        public function getOpenAPI(): array {
            return parent::getOpenAPI();
        }

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
            $this->validation(['format' => $sFormat]);
            return $this;
        }

        public function pattern(string $sFormat): self {
            $this->validation(['pattern' => $sFormat]);
            return $this;
        }

        public function minLength(int $iMinimum): self {
            $this->validation(['minLength' => $iMinimum]);
            return $this;
        }

        public function maxLength(int $iMaximum): self {
            $this->validation(['maxLength' => $iMaximum]);
            return $this;
        }
    }