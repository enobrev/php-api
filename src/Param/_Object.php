<?php
    namespace Enobrev\API\Param;
    
    use Enobrev\API\Exception;
    use Enobrev\API\JsonSchemaInterface;
    use Enobrev\API\Param;

    class _Object extends Param {
        public static function create(): self {
            return new self();
        }

        public function __construct() {
            parent::__construct(Param::OBJECT);
        }

        /**
         * @param Param[] $aItems
         * @return _Object
         */
        public function items(array $aItems): self {
            $this->validation(['items' => $aItems]);
            return $this;
        }

        public function getJsonSchema(): array {
            if (!isset($this->aValidation['items'])) {
                throw new Exception('Object Param requires items definition');
            }

            $aSchema = $this->getValidationForSchema();
            $aSchema['type'] = $this->getType();
            $aSchema['additionalProperties'] = false;
            $aSchema['properties'] = [];
            foreach($aSchema['items'] as $sParam => $oItem) {
                if ($oItem instanceof JsonSchemaInterface) {
                    $aSchema['properties'][$sParam] = $oItem->getJsonSchema();
                } else {
                    $aSchema['properties'][$sParam] = $oItem;
                }
            }
            unset($aSchema['items']);
            return $aSchema;
        }

        public function getOpenAPI(): array {
            return parent::getOpenAPI();
        }
    }