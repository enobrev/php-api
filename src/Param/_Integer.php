<?php
    namespace Enobrev\API\Param;
    
    use Enobrev\API\Param;

    class _Integer extends Param {
        public static function create(): self {
            return new self();
        }

        public function __construct() {
            parent::__construct(Param::INTEGER);
        }

        public function getJsonSchema(): array {
            return parent::getJsonSchema();
        }

        public function getOpenAPI(): array {
            return parent::getOpenAPI();
        }

        public function minimum(int $iMinimum, $bExclusive = false): self {
            $this->validation(['minimum' => $iMinimum]);
            if ($bExclusive) {
                $this->validation(['exclusiveMinimum' => $bExclusive]);
            }
            return $this;
        }

        public function maximum(int $iMaximum, $bExclusive = false): self {
            $this->validation(['maximum' => $iMaximum]);
            if ($bExclusive) {
                $this->validation(['exclusiveMaximum' => $bExclusive]);
            }
            return $this;
        }
    }