<?php
    namespace Enobrev\API\Param;

    use Enobrev\Log;
    use stdClass;

    use cebe\openapi\spec\Schema;
    use Enobrev\API\FullSpec\Component\Reference;

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

        public function getSchema(): Schema {
            $aSchema         = $this->aValidation;
            $aSchema['type'] = $this->getType();

            $aRequired = [];

            if ($this->hasItems()) {
                unset($aSchema['items']);

                $aSchema['additionalProperties'] = $this->allowsAdditionalProperties();
                $aSchema['properties'] = [];
                foreach ($this->getItems() as $sParam => $mItem) {
                    if ($mItem instanceof Param) {
                        $aSchema['properties'][$sParam] = $mItem->getSchema();
                        if ($mItem->isRequired()) {
                            $aRequired[] = $sParam;
                        }
                    } else if ($mItem instanceof Schema) {
                        $aSchema['properties'][$sParam] = $mItem;
                        if ($mItem->required) {
                            $aRequired[] = $sParam;
                        }
                    } else if ($mItem instanceof Reference) {
                        $aSchema['properties'][$sParam] = $mItem->getSpecObject();
                    } else if (is_array($mItem)) {
                        $aSchema['properties'][$sParam] = Spec::arrayToSchema($mItem);
                    } else {
                        Log::e('Param\_Object.getSchema.Unknown', ['param' => $sParam, 'item' => $mItem]);
                    }
                }
            } else {
                $aSchema['additionalProperties'] = true;
            }

            if ($this->sDescription) {
                $aSchema['description'] = $this->sDescription;
            }

            if (count($aRequired)) {
                $aSchema['required'] = $aRequired;
            }

            if ($this->isDeprecated()) {
                $aSchema['deprecated'] = true;
            }

            if ($this->isNullable()) {
                $aSchema['nullable'] = true;
            }

            return new Schema($aSchema);
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

        /**
         * @param Schema $oSchema
         *
         * @return self
         */
        public static function createFromSchema(Schema $oSchema): self  {
            $oParam = self::create();

            if ($oSchema->properties) {
                $aItemParams = [];
                foreach($oSchema->properties as $sParam => $oItemSchema) {
                    $aItemParams[$sParam] = Param::createFromSchema($oItemSchema);
                }
                $oParam = $oParam->items($aItemParams);
            }

            return $oParam;
        }
    }