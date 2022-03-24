<?php
    namespace Enobrev\API;

    use ArrayIterator;

    use DateTime;
    use Money\Money;

    use Enobrev\ORM;

    class DataMap {
        private static ?array $DATA = null;

        private static string $sDataFile;

        /**
         * @return array|mixed
         */
        private static function getData() {
            assert(self::$sDataFile !== null, new Exception\MissingDataMapDefinition());

            if (self::$DATA === null) {
                $sContents = file_get_contents(self::$sDataFile);
                if ($sContents) {
                    self::$DATA = json_decode($sContents, true);
                }
            }

            return self::$DATA;
        }

        /**
         * @param string $sPath
         *
         * @return mixed
         */
        private static function getMap(string $sPath) {
            $aData = self::getData();

            assert(isset($aData[$sPath]), new Exception\InvalidDataMapPath($sPath));

            return $aData[$sPath];
        }

        /**
         * @param string $sPath
         *
         * @return bool
         */
        public static function hasClassPath(string $sPath): bool {
            $aMap = self::getMap('_CLASSES_');
            return isset($aMap[$sPath]);
        }

        /**
         * @param string $sPath
         *
         * @return string
         */
        public static function getClassName(string $sPath): ?string {
            $aMap = self::getMap('_CLASSES_');
            return $aMap[$sPath] ?? null;
        }

        /**
         * @param $oClass
         *
         * @return string
         */
        public static function getClassPath($oClass): ?string {
            $aMap     = self::getMap('_CLASSES_');
            $aFlipped = array_flip($aMap);

            $sClass   = get_class($oClass);
            $aClass   = explode('\\', $sClass);
            $sClass   = array_pop($aClass);

            return $aFlipped[$sClass] ?? null;
        }

        /**
         * @param string $sDataFile
         */
        public static function setDataFile(string $sDataFile):void {
            self::$sDataFile = $sDataFile;
        }

        /**
         * @param string      $sPath
         * @param ArrayIterator|ORM\Table[]|ORM\Tables $oData
         * @param string|null $sKeyField
         *
         * @return array|ORM\Field[][]
         */
        public static function getIndexedResponseMaps(string $sPath, $oData, ?string $sKeyField = null): array {
            $aResponse = [];
            foreach($oData as $oDatum) {
                /** @noinspection AdditionOperationOnArraysInspection */
                $aResponse += self::getIndexedResponseMap($sPath, $oDatum, $sKeyField);
            }

            return $aResponse;
        }

        /**
         * @param string      $sPath
         * @param ORM\Table   $oDatum
         * @param string|null $sKeyField
         *
         * @return ORM\Field[][]
         */
        public static function getIndexedResponseMap(string $sPath, ORM\Table $oDatum, ?string $sKeyField = null): array {
            if (!$sKeyField) {
                $sKeyField = $oDatum->getPrimary()[0]->sColumn;
            }

            return [
                $oDatum->$sKeyField->getValue() => self::getResponseMap($sPath, $oDatum)
            ];
        }

        /**
         * @param string    $sPath
         * @param ORM\Table $oDatum
         *
         * @return ORM\Field[]
         */
        public static function getResponseMap(string $sPath, ORM\Table $oDatum): array {
            $aMap         = self::getMap($sPath);
            $aResponseMap = [];
            foreach($aMap as $sPublicField => $sTableField) {
                if ($oDatum->$sTableField instanceof ORM\Field) {
                    $aResponseMap[$sPublicField] = $oDatum->$sTableField;
                }
            }
            return $aResponseMap;
        }

        /**
         * // got this Generics tip from https://medium.com/@ondrejmirtes/generics-in-php-using-phpdocs-14e7301953
         * @template T of ORM\Table
         * @param T $oTable
         * @param array     $aPostParams
         *
         * @return T
         */
        public static function applyPostParamsToTable(ORM\Table $oTable, array $aPostParams): ORM\Table {
            $aMap   = self::getResponseMap($oTable->getTitle(), $oTable);
            $oTable->mapArrayToFields($aPostParams, $aMap);
            return $oTable;
        }

        /**
         * @param ORM\Table  $oTable
         * @param array|null $aExcludedFields
         *
         * @return array
         */
        public static function convertTableToResponseArray(ORM\Table $oTable, ?array $aExcludedFields = null): array {
            $aMap         = self::getMap($oTable->getTitle());
            $aResponseMap = [];
            foreach($aMap as $sPublicField => $sTableField) {
                if ($aExcludedFields) {
                    if (in_array($sTableField, $aExcludedFields, true) !== false) {
                        continue;
                    }

                    if (in_array($sPublicField, $aExcludedFields, true) !== false) {
                        continue;
                    }
                }

                if ($oTable->$sTableField instanceof ORM\Field === false) {
                    continue;
                }

                $mValue = $oTable->$sTableField->getValue();

                switch(true) {
                    case $oTable->$sTableField instanceof ORM\Field\JSONObject:
                        if ($oTable->$sTableField->hasValue()) {
                            $mValue = (object)json_decode($oTable->$sTableField->getValue(), false);
                        } else {
                            $mValue = null;
                        }
                        break;

                    case $oTable->$sTableField instanceof ORM\Field\JSONArray:
                    case $oTable->$sTableField instanceof ORM\Field\JSONText:
                        $mValue = json_decode($oTable->$sTableField->getValue(), false);
                        break;

                    case $oTable->$sTableField instanceof ORM\Field\Date:
                    case $oTable->$sTableField instanceof ORM\Field\DateTime:
                        if ($oTable->$sTableField->isNull()) {
                            $mValue = null;
                        } else {
                            $mValue = (string) $oTable->$sTableField;
                        }
                        break;
                }

                switch(true) {
                    case $mValue instanceof DateTime:
                        $mValue = $mValue->format(DateTime::RFC3339);
                        break;

                    case $mValue instanceof Money:
                        $mValue = $mValue->getAmount();
                        break;
                }

                $aResponseMap[$sPublicField] = $mValue;
            }

            return $aResponseMap;
        }

        /**
         * @param ORM\Table $oDatum
         * @param string    $sPublicField
         *
         * @return mixed
         */
        public static function getField(ORM\Table $oDatum, string $sPublicField) {
            $aMap          = self::getMap($oDatum->getTitle());

            if ($sPublicField && isset($aMap[$sPublicField])) {
                $sPrivateField = $aMap[$sPublicField];

                return $oDatum->$sPrivateField instanceof ORM\Field ? $oDatum->$sPrivateField : null;
            }
            
            return null;
        }

        /**
         * @param ORM\Table $oDatum
         * @param string    $sPrivateField
         *
         * @return null|string
         */
        public static function getPublicName(ORM\Table $oDatum, string $sPrivateField): ?string {
            if ($oDatum->$sPrivateField instanceof ORM\Field) {
                $aMap = array_flip(self::getMap($oDatum->getTitle()));
                return $aMap[$sPrivateField] ?? null;
            }

            return null;
        }

        /**
         * @param ORM\Table $oBaseTable
         * @param null|string $sSearch
         *
         * @return array|null
         * @throws Exception
         */
        public static function convertSearchTablesToORMTables(ORM\Table $oBaseTable, ?string $sSearch): ?array {
            if (!$sSearch) {
                return null;
            }

            $aSearch = ORM\Tables::searchTermPreProcess($sSearch);

            if (count($aSearch)) {
                foreach ($aSearch['conditions'] as &$aCondition) {
                    if (isset($aCondition['field'])) {
                        $oSearchField = self::getField($oBaseTable, $aCondition['field']);
                        if ($oSearchField instanceof ORM\Field) {
                            $aCondition['field'] = $oSearchField->sColumn;
                        } else {
                            throw new Exception('Invalid Field For Search ' . $aCondition['field']);
                        }
                    }
                }
            }

            return $aSearch;
        }

        /**
         * @param ORM\Table $oBaseTable
         * @param null|string $sSort
         *
         * @return array|null
         * @throws Exception
         */
        public static function convertSortTablesToORMTables(ORM\Table $oBaseTable, ?string $sSort): ?array {
            if (!$sSort) {
                return null;
            }

            $aSort = ORM\Tables::sortTermPreProcess($sSort);

            if (count($aSort)) {
                foreach ($aSort as &$aPair) {
                    if (isset($aPair['table'])) {
                        $sSortTableClass = ORM\Tables::getNamespacedTableClassName(self::getClassName($aPair['table']));
                        /** @var ORM\Table $oSortTable */
                        $oSortTable = new $sSortTableClass();
                        if (!$oSortTable instanceof ORM\Table) {
                            throw new Exception('Invalid Table For Sort ' . $sSortTableClass);
                        }

                        $aPair['table'] = $sSortTableClass;
                        $oSortField = self::getField($oSortTable, $aPair['field']);
                        if ($oSortField instanceof ORM\Field) {
                            $aPair['field'] = $oSortField->sColumn;
                        } else {
                            throw new Exception('Invalid Field For Sort ' . $aPair['field']);
                        }
                    } else {
                        $oSortField = self::getField($oBaseTable, $aPair['field']);
                        if ($oSortField instanceof ORM\Field) {
                            $aPair['field'] = $oSortField->sColumn;
                        } else {
                            throw new Exception('Invalid Field For Sort ' . $aPair['field']);
                        }
                    }
                }
            }

            return $aSort;
        }
    }