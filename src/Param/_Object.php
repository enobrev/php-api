<?php
    namespace Enobrev\API\Param;
    
    use Enobrev\API\Exception;
    use Enobrev\API\JsonSchemaInterface;
    use Enobrev\API\Param;
    use Enobrev\API\ParamTrait;
    use Enobrev\API\Spec;

    class _Object extends Param {
        use ParamTrait;

        /** @var string */
        protected $sType = Param::OBJECT;

        /**
         * @param Param[] $aItems
         * @return _Object
         */
        public function items(array $aItems): self {
            return $this->validation(['items' => $aItems]);
        }

        public function getJsonSchema(): array {
            if (!isset($this->aValidation['items'])) {
                throw new Exception('Object Param requires items definition');
            }

            $aSchema = $this->getValidationForSchema();
            $aSchema['type'] = $this->getType();
            $aSchema['additionalProperties'] = false;
            $aSchema['properties'] = [];
            foreach($aSchema['items'] as $sParam => $mItem) {
                if ($mItem instanceof JsonSchemaInterface) {
                    $aSchema['properties'][$sParam] = $mItem->getJsonSchema();
                } else if (is_array($mItem)) {
                    $aSchema['properties'][$sParam] = Spec::toJsonSchema($mItem);
                } else {
                    $aSchema['properties'][$sParam] = $mItem;
                }
            }
            unset($aSchema['items']);

            if ($this->sDescription) {
                $aSchema['description'] = $this->sDescription;
            }

            return $aSchema;
        }

        public function getJsonSchemaForOpenAPI(): array {
            return parent::getJsonSchemaForOpenAPI();
        }
    }