<?php
    namespace Enobrev\API\Param;
    
    use Enobrev\API\Exception;
    use Enobrev\API\JsonSchemaInterface;
    use Enobrev\API\Param;
    use Enobrev\API\Spec;

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

        public function getOpenAPI(): array {
            return parent::getOpenAPI();
        }
    }