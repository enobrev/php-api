<?php
    namespace Enobrev\API;

    use ArrayIterator;
    use Enobrev\API\Exception;
    use Enobrev\ORM;

    class DataMap {
        /**
         * @var array
         */
        private static $DATA = null;

        /**
         * @var string
         */
        private static $sDataFile = null;

        /**
         * @return array|mixed
         * @throws Exception\MissingDataMapDefinition
         */
        private static function getData() {
            if (self::$sDataFile === null) {
                throw new Exception\MissingDataMapDefinition();
            }

            if (self::$DATA === null) {
                self::$DATA = json_decode(file_get_contents(self::$sDataFile), true);
            }

            return self::$DATA;
        }

        /**
         * @param string $sPath
         * @return mixed
         * @throws Exception\InvalidDataMapPath
         */
        private static function getMap(string $sPath) {
            $aData = self::getData();
            if (isset($aData[$sPath])) {
                return $aData[$sPath];
            }

            throw new Exception\InvalidDataMapPath($sPath);
        }

        /**
         * @param string $sPath
         * @return bool
         */
        public static function hasClassPath($sPath) {
            $aMap = self::getMap('_CLASSES_');
            return isset($aMap[$sPath]);
        }

        /**
         * @param string $sPath
         * @return string
         */
        public static function getClassName($sPath) {
            $aMap = self::getMap('_CLASSES_');
            return $aMap[$sPath] ?? null;
        }

        /**
         * @param string $sDataFile
         */
        public static function setDataFile(string $sDataFile) {
            self::$sDataFile = $sDataFile;
        }

        /**
         * @param string
         * @param ArrayIterator|ORM\Table[]|ORM\Tables $oData
         * @return array
         */
        public static function getIndexedResponseMaps($sPath, $oData) {
            $aResponse = [];
            foreach($oData as $oDatum) {
                $aResponse += self::getIndexedResponseMap($sPath, $oDatum);
            }

            return $aResponse;
        }

        /**
         * @param string $sPath
         * @param ORM\Table $oDatum
         * @return array
         */
        public static function getIndexedResponseMap(string $sPath, ORM\Table $oDatum) {
            return [
                $oDatum->getPrimary()[0]->getValue() => self::getResponseMap($sPath, $oDatum)
            ];
        }

        /**
         * @param string $sPath
         * @param ORM\Table $oDatum
         * @return array
         */
        public static function getResponseMap(string $sPath, ORM\Table $oDatum) {
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
         * @param ORM\Table $oDatum
         * @param string    $sPublicField
         * @return mixed
         */
        public static function getField(ORM\Table $oDatum, string $sPublicField) {
            $aMap          = self::getMap($oDatum->getTitle());
            $sPrivateField = $aMap[$sPublicField];

            return $oDatum->$sPrivateField instanceof ORM\Field ? $oDatum->$sPrivateField : null;
        }

        /**
         * @param ORM\Table $oDatum
         * @param string    $sPrivateField
         * @return null|string
         */
        public static function getPublicName(ORM\Table $oDatum, string $sPrivateField) {
            if ($oDatum->$sPrivateField instanceof ORM\Field) {
                $aMap = array_flip(self::getMap($oDatum->getTitle()));
                return $aMap[$sPrivateField] ?? null;
            }

            return null;
        }
    }