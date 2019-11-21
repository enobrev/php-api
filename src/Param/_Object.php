<?php
    namespace Enobrev\API\Param;

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

        public function hasItems(): bool {
            return isset($this->aValidation['items']);
        }

        public function getItems() {
            return $this->aValidation['items'];
        }

        public function allowsAdditionalProperties(): bool {
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

                    if (($mItem instanceof Param) && $mItem->isRequired()) {
                        $aRequired[] = $sParam;
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

        /**
         * Heavily inspired by justinrainbow/json-schema, except tries not to coerce nulls into non-nulls
         * @param $mValue
         * @return stdClass
         */
        public function coerce($mValue) {
            if ($this->isNullable()) {
                if ($mValue === null || $mValue === 'null' || $mValue === 0 || $mValue === false || $mValue === '') {
                    return null;
                }
            }

            if (is_array($mValue)) {
                $mValue = (object) $mValue;
            }

            if (isset($this->aValidation['items']) && is_array($this->aValidation['items'])) {
                foreach ($mValue as $sProperty => &$mItem) {
                    if (isset($this->aValidation['items'][$sProperty])) {
                        $oParam = $this->aValidation['items'][$sProperty];
                        if ($oParam instanceof Param) {
                            $mItem = $oParam->coerce($mItem);
                        }
                    }
                }
            }

            return $mValue;
        }
    }