<?php
    namespace Enobrev\API\Param;
    
    use cebe\openapi\spec\Schema;
    use Enobrev\API\Param;
    use Enobrev\API\ParamTrait;

    class _Array extends Param {
        use ParamTrait;

        protected string $sType = Param::ARRAY;

        public function items(Param | Schema $oItems): self {
            return $this->validation(['items' => $oItems]);
        }

        public function minItems(int $iMinItems): self {
            return $this->validation(['minItems' => $iMinItems]);
        }

        public function maxItems(int $iMaxItems): self {
            return $this->validation(['maxItems' => $iMaxItems]);
        }

        public function uniqueItems(bool $bUniqueItems = true): self {
            return $this->validation(['uniqueItems' => $bUniqueItems]);
        }

        public function getSchema(): Schema {
            $aSchema = $this->aValidation;

            $mItems = $aSchema['items'] ?? null;
            if ($mItems instanceof Param) {
                $aSchema['items'] = $aSchema['items']->getSchema();
            } else if ($mItems instanceof Schema) {
                $aSchema['items'] = $aSchema['items'];
            }

            if ($this->isDeprecated()) {
                $aSchema['deprecated'] = true;
            }

            if ($this->isNullable()) {
                $aSchema['nullable'] = true;
            }

            $aSchema['type'] = $this->getType();

            if ($this->sDescription) {
                $aSchema['description'] = $this->sDescription;
            }

            if (!empty($this->sExample)) {
                $aSchema['example'] = $this->sExample;
            }

            return new Schema($aSchema);
        }

        /**
         * Heavily inspired by justinrainbow/json-schema, except tries not to coerce nulls into non-nulls
         * @param $mValue
         * @return array
         */
        public function coerce($mValue) {
            if ($this->isNullable()) {
                if ($mValue === null || $mValue === 'null' || $mValue === 0 || $mValue === false || $mValue === '') {
                    return null;
                }
            }

            if (is_scalar($mValue) && strpos($mValue, ',') !== false) {
                $mValue = explode(',', $mValue);
                $mValue = array_map('trim', $mValue);
            }

            if (is_scalar($mValue) || $mValue === null) {
                $mValue = [$mValue];
            }

            if (is_array($mValue) && isset($this->aValidation['items'])) {
                $oItems = $this->aValidation['items'];
                if ($oItems instanceof Param) {
                    foreach ($mValue as &$mItem) {
                        $mItem = $oItems->coerce($mItem);
                    }
                }
            }

            return $mValue;
        }

        /**
         * @param Schema $oSchema
         * @return self
         */
        public static function createFromSchema(Schema $oSchema): self {
            $oParam = self::create();

            if ($oSchema->minItems) {
                $oParam = $oParam->minItems($oSchema->minItems);
            }

            if ($oSchema->maxItems) {
                $oParam = $oParam->maxItems($oSchema->maxItems);
            }

            if ($oSchema->uniqueItems) {
                $oParam = $oParam->uniqueItems($oSchema->uniqueItems);
            }

            if ($oSchema->items) {
                $oParam = $oParam->items(Param::createFromSchema($oSchema->items));
            }

            return $oParam;
        }
    }