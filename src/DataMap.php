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
                $sContents = file_get_contents(self::$sDataFile);
                if ($sContents) {
                    self::$DATA = json_decode($sContents, true);
                }
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
         * @param $oClass
         * @return string
         */
        public static function getClassPath($oClass) {
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
         * @param string $sPath
         * @param ArrayIterator|ORM\Table[]|ORM\Tables $oData
         * @return array
         */
        public static function getIndexedResponseMaps(string $sPath, $oData): array {
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
        public static function getIndexedResponseMap(string $sPath, ORM\Table $oDatum): array {
            return [
                $oDatum->getPrimary()[0]->getValue() => self::getResponseMap($sPath, $oDatum)
            ];
        }

        /**
         * @param string $sPath
         * @param ORM\Table $oDatum
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