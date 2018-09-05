<?php
    namespace Enobrev\API\Param;
    
    use Enobrev\API\Param;
    use Enobrev\API\ParamTrait;

    class _Boolean extends Param {
        use ParamTrait;

        /** @var string */
        protected $sType = Param::BOOLEAN;

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