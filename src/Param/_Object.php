<?php
    namespace Enobrev\API\Param;

    use Enobrev\API\OpenApiInterface;
    use stdClass;

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

        public function hasItems() {
            return isset($this->aValidation['items']);
        }

        public function getItems() {
            return $this->aValidation['items'];
        }

        public function allowsAdditionalProperties() {
            return isset($this->aValidation['additionalProperties']) && (bool) $this->aValidation['additionalProperties'];
        }

        public function additionalProperties(bool $bAdditionalProperties): self {
            return $this->validation(['additionalProperties' => $bAdditionalProperties]);
        }

        public function getJsonSchema($bOpenSchema = false): array {
            $aSchema = $this->getValidationForSchema();
            $aSchema['type'] = $this->getType();
            $aRequired = [];

            if (isset($aSchema['items'])) {
                $aSchema['additionalProperties'] = $this->allowsAdditionalProperties();
                $aSchema['properties'] = [];
                foreach ($aSchema['items'] as $sParam => $mItem) {
                    if ($bOpenSchema && $mItem instanceof Param) {
                        $aSchema['properties'][$sParam] = $mItem->getJsonSchemaForOpenAPI();
                    } else if ($mItem instanceof JsonSchemaInterface) {
                        $aSchema['properties'][$sParam] = $mItem->getJsonSchema();
                    } else if (is_array($mItem)) {
                        $aSchema['properties'][$sParam] = Spec::toJsonSchema($mItem);
                    } else {
                        $aSchema['properties'][$sParam] = $mItem;
                    }

                    if ($mItem instanceof Param) {
                        if ($mItem->isRequired()) {
                            $aRequired[] = $sParam;
                        }
                    }
                }
                unset($aSchema['items']);
            } else {
                $aSchema['additionalProperties'] = true;
            }

            if ($this->sDescription) {
                $aSchema['description'] = $this->sDescription;
            }

            if (count($aRequired)) {
                $aSchema['required'] = $aRequired;
            }

            return $aSchema;
        }

        public function getJsonSchemaForOpenAPI(): array {
            return parent::getJsonSchemaForOpenAPI();
        }

        /**
         * Heavily inspired by justinrainbow/json-schema, except tries not to coerce nulls into non-nulls
         * @param $mValue
         * @return stdClass
         */
        public function coerce($mValue) {
            if ($this->isNullable()) {
                if (is_null($mValue) || $mValue == 'null' || $mValue === 0 || $mValue === false || $mValue === '') {
                    return null;
                }
            }

            if (is_array($mValue)) {
                return (object) $mValue;
            }

            return $mValue;
        }
    }