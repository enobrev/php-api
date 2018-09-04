<?php
    namespace Enobrev\API\Param;
    
    use Enobrev\API\Param;

    class _Boolean extends Param {
        public static function create(): self {
            return new self();
        }

        public function __construct() {
            parent::__construct(Param::BOOLEAN);
        }

        public function default($bDefault):Param {
            return $this->validation(['default' => (bool) $bDefault]);
        }

        public function getJsonSchema(): array {
            return parent::getJsonSchema();
        }

        public function getOpenAPI(): array {
            return parent::getOpenAPI();
        }
    }