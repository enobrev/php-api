<?php
    namespace Enobrev\API;

    use PDO;

    use Enobrev\Log;
    use Enobrev\ORM;
    use Enobrev\ORM\Table;
    use Enobrev\SQL;
    use Enobrev\SQLBuilder;

    trait Restful {
        /** @var string */
        private static $sNamespaceTable = null;

        /** @var  Request */
        public $Request;

        /** @var  Response */
        public $Response;

        /** @var ORM\ModifiedDateColumn[]|ORM\ModifiedDateColumn|ORM\Table|ORM\Tables */
        protected $Data;

        /** @var  string */
        protected $sPath;

        /**
         * @param string $sNamespaceTable
         */
        public static function init(string $sNamespaceTable) {
            self::$sNamespaceTable = trim($sNamespaceTable, '\\');
        }

        /**
         * Set the Data upon Which the Response is built
         * @param ORM\Table|ORM\Tables $oData
         * @throws \Exception
         */
        public function setData($oData) {
            if ($oData instanceof ORM\Table) {
                $this->sPath = $oData->getTitle();
            } else if ($oData instanceof ORM\Tables) {
                $this->sPath = $oData->getTable()->getTitle();
            }

            $this->Data  = $oData;
        }

        /**
         * @return bool
         */
        public function hasData() {
            return $this->Data instanceof ORM\Table
                || $this->Data instanceof ORM\Tables;
        }

        /**
         * @return ORM\ModifiedDateColumn|ORM\ModifiedDateColumn[]|Table|ORM\Tables
         */
        public function getData() {
            return $this->Data;
        }

        /**
         * HTTP HEAD
         */
        public function head() {
            if ($this->Data instanceof ORM\Tables) {
                $this->heads();
                return;
            }

            if ($this->Data instanceof ORM\Table) {
                $this->Response->setHeadersFromTable($this->Data);
                return;
            }

            $this->Response->statusNoContent();
            return;
        }

        /**
         * HTTP HEAD for Multiple Records
         */
        protected function heads() {
            $this->Response->setLastModifiedFromTables($this->Data);
            $this->Response->statusNoContent();
            return;
        }

        /**
         * HTTP GET
         */
        public function get() {
            if ($this->Data instanceof ORM\Tables) {
                $this->gets();
                return;
            }

            if ($this->Data instanceof ORM\Table === false) {
                $this->Response->statusNotFound();
                return;
            }

            $this->Response->add($this->sPath, DataMap::getIndexedResponseMap($this->sPath, $this->Data));
            $this->Response->add('counts.' . $this->sPath, 1);
            $this->Response->add('sorts.' .  $this->sPath, [$this->Data->getPrimary()[0]->getValue()]);
            $this->Response->setHeadersFromTable($this->Data);
            return;
        }

        /**
         * HTTP GET for Multiple Records
         */
        protected function gets() {
            if ($this->Data->count() > 0) {
                $this->Response->add($this->sPath, DataMap::getIndexedResponseMaps($this->sPath, $this->Data));
                $this->Response->add('sorts.' . $this->sPath, $this->getSorts());
                $this->Response->setLastModifiedFromTables($this->Data);
            } else {
                $this->Response->statusNotFound();
            }
            return;
        }

        /**
         * @return array
         */
        protected function getSorts() {
            $aSorts = [];
            /** @var ORM\Table $oTable */
            foreach($this->Data as $oTable) {
                $aSorts[] = $oTable->getPrimary()[0]->getValue();
            }

            return $aSorts;
        }

        /**
         * HTTP POST
         */
        public function post() {
            if ($this->Data instanceof ORM\Table === false) {
                $this->Response->statusBadRequest();
                return;
            }

            $this->handleFiles();

            $aOverrides = $this->overridePrimaries();

            if (empty((array) $this->Data->oResult)) { // new record
                foreach ($this->Data->getFields() as $oField) { // Set By Router
                    if ($oField->hasValue() && !$oField->isDefault()) {
                        $aOverrides[$oField->sColumn] = $oField->getValue();
                    }
                }
            }

            Log::d('API.Restful.post', [
                'this'          => get_class($this),
                'data'          => get_class($this->Data),
                'attributes'    => $this->Request->OriginalRequest->getAttributes(),
                'post'          => json_encode($this->Request->POST),
                'mapped'        => json_encode(DataMap::getResponseMap($this->sPath, $this->Data)),
                'overrides'     => json_encode($aOverrides)
            ]);

            /** @var ORM\Table $oTable */
            $oTable = get_class($this->Data);
            $this->Data = $oTable::createAndUpdateFromMap(
                $this->Request->POST,
                DataMap::getResponseMap($this->sPath, $this->Data),
                $aOverrides
            );

            $this->get();
        }

        /**
         * HTTP PUT
         */
        public function put() {
            if ($this->Data instanceof ORM\Table === false) {
                $this->Response->statusNotFound();
                return;
            }

            $this->handleFiles();

            $aOverridePrimaries = [];
            foreach($this->Data->getPrimary() as $oPrimary) {
                $sColumn = $oPrimary->sColumn;
                $aOverridePrimaries[$sColumn] = $this->Data->$sColumn;
            }

            $aOverridePrimaries = $this->overridePrimaries($aOverridePrimaries);

            Log::d('API.Restful.put', [
                'this'       => get_class($this),
                'data'       => get_class($this->Data),
                'attributes' => $this->Request->OriginalRequest->getAttributes(),
                'put'        => json_encode($this->Request->PUT),
                'mapped'     => json_encode(DataMap::getResponseMap($this->sPath, $this->Data)),
                'overrides'  => json_encode($aOverridePrimaries)
            ]);

            /** @var ORM\Table $oTable */
            $oTable = get_class($this->Data);
            $this->Data = $oTable::createAndUpdateFromMap(
                $this->Request->PUT,
                DataMap::getResponseMap($this->sPath, $this->Data),
                $aOverridePrimaries
            );

            $this->get();
        }

        /**
         * Grabs Attributes from URI and overrides Fields in POST or PUT data
         * @param array $aOverridePrimaries
         * @return array
         */
        protected function overridePrimaries(array $aOverridePrimaries = []) {
            $aAttributes = $this->Request->OriginalRequest->getAttributes();
            foreach($aAttributes as $sField => $sValue) {
                $oField = $this->Data->$sField;
                if ($oField instanceof ORM\Field) {
                    $aOverridePrimaries[$sField] = $sValue;
                }
            }

            return $aOverridePrimaries;
        }

        /**
         * HTTP DELETE
         */
        public function delete() {
            if ($this->Data instanceof ORM\Table === false) {
                $this->Response->statusNotFound();
                return;
            }

            $this->Data->delete();
            $this->Response->statusNoContent();
            return;
        }

        protected function handleFiles() {

        }

        /**
         * @throws Exception\InvalidReference
         * @throws Exception\InvalidTable
         * @throws \Enobrev\API\Exception
         */
        public function setDataFromPath() {
            $aPairs = $this->Request->getPathPairs();

            if (count($aPairs) > 0) {
                $aLastPair = array_pop($aPairs);
                $bHasClass = DataMap::getClassName($aLastPair[0]) !== null;

                if ($this->Request->isPost() && !isset($aLastPair[1])) {
                    Log::d('API.Restful._getResultsFromPath.Post.NoId');

                    $oTable = $this->_getPrimaryTableFromPath();

                    // Prefill empty POST object with url params
                    while (count($aPairs) > 0) {
                        $aPart      = array_shift($aPairs);
                        $sClassName = DataMap::getClassName($aPart[0]);
                        $sClass     = self::_getNamespacedTableClassName($sClassName);

                        /** @var ORM\Table $oWhereTable */
                        $oWhereTable = new $sClass();

                        if ($oWhereTable instanceof ORM\Table === false) {
                            throw new Exception('Invalid Where Table in Path');
                        }

                        if (isset($aPart[1])) {
                            $oReference = $oTable->getFieldThatReferencesTable($oWhereTable);
                            if ($oReference instanceof ORM\Field === false) {
                                throw new Exception\InvalidReference("Cannot Associate " . (new \ReflectionClass($oTable))->getShortName() . ' with ' . (new \ReflectionClass($oWhereTable))->getShortName());
                            }

                            $oReference->setValue($aPart[1]);
                            $this->Request->updateParam($oReference->sColumn, $oReference->getValue());
                        }
                    }

                    $this->setData($oTable);
                } else if ($bHasClass) {
                    $oQuery = $this->_getQueryFromPath();

                    $oDb = ORM\Db::getInstance();
                    if ($oResults = $oDb->namedQuery('getQueryFromPath', $oQuery)) {
                        $iRows  = $oDb->getLastRowsAffected();
                        $oTable = $this->_getPrimaryTableFromPath();

                        if ($iRows == 1) {
                            Log::d('API.Restful._getResultsFromPath.FoundOne');

                            $this->setData($oTable->createFromPDOStatement($oResults));
                        } else if ($iRows > 1) {
                            Log::d('API.Restful._getResultsFromPath.FoundMultiple');

                            $oTables = $oTable::getTables();
                            $this->setData(new $oTables($oResults->fetchAll(PDO::FETCH_CLASS, get_class($oTable))));

                            // Add the count to the dynamic query output
                            if ($oQuery instanceof SQLBuilder) {
                                $oQuery->setType(SQLBuilder::TYPE_COUNT);

                                if ($oResult = ORM\Db::getInstance()->namedQuery('getCountQueryFromPath', $oQuery)) {
                                    $iCount = $oResult->fetchColumn();
                                    if ($iCount !== false) {
                                        $this->Response->add('counts.' . $oTable->getTitle(), (int) $iCount);
                                    }
                                }
                            }
                        } else if ($this->Request->isPost()) {
                            Log::d('API.Restful._getResultsFromPath.FoundNone.Post');

                            $aPrimary = $oTable->getPrimaryFieldNames();
                            if (count($aPrimary) == 1) {
                                $sPrimary = array_shift($aPrimary);
                                $oTable->$sPrimary->setValue($aLastPair[1]);
                            }

                            $this->setData($oTable);
                        }
                    } else {
                        throw new Exception('No Matching Path to Grab Results From');
                    }
                }
            } else {
                throw new Exception('No Pairs to Grab Results From');
            }
        }

        /**
         * @return SQL|SQLBuilder|string
         * @throws Exception\InvalidReference
         * @throws Exception\InvalidTable
         * @throws \Enobrev\API\Exception
         */
        private function _getQueryFromPath() {
            $oTable    = $this->_getPrimaryTableFromPath();

            Log::d('API.Restful._getQueryFromPath', [
                'table_class'   => get_class($oTable),
                'table'         => $oTable->getTitle()
            ]);

            $oQuery = SQLBuilder::select($oTable);

            $aPairs    = $this->Request->getPathPairs();
            $aLastPair = array_pop($aPairs);
            if (isset($aLastPair[1])) {
                $oQuery->eq_in($oTable->getPrimary()[0], $aLastPair[1]);
                $this->Request->updateParam($oTable->getPrimary()[0]->sColumn, $aLastPair[1]);
            }

            while (count($aPairs) > 0) {
                Log::d('API.Restful._getQueryFromPath.Pairs', ['pairs' => json_encode($aPairs)]);

                $aPart = array_shift($aPairs);
                $sClassName = DataMap::getClassName($aPart[0]);
                $sClass = self::_getNamespacedTableClassName($sClassName);

                /** @var ORM\Table $oWhereTable */
                $oWhereTable = new $sClass();

                if ($oWhereTable instanceof ORM\Table === false) {
                    throw new Exception('Invalid Where Table in Query Path');
                }

                if (isset($aPart[1])) {
                    Log::d('API.Restful._getQueryFromPath.Pairs.PartWithValue', ['part' => json_encode($aPart)]);
                    $oReference = $oTable->getFieldThatReferencesTable($oWhereTable);
                    if ($oReference instanceof ORM\Field === false) {
                        throw new Exception\InvalidReference("Cannot Associate " . (new \ReflectionClass($oTable))->getShortName() . ' with ' . (new \ReflectionClass($oWhereTable))->getShortName());
                    }

                    $oQuery->eq_in($oReference, $aPart[1]);
                    $this->Request->updateParam($oReference->sColumn, $aPart[1]);

                    Log::d('API.Restful._getQueryFromPath.Pairs.AddingAttribute', [
                        'field' => $oReference->sColumn,
                        'value' => $aPart[1]
                    ]);
                }
            }

            if (isset($aLastPair[1])) {
                Log::d('API.Restful._getQueryFromPath.Querying.HasID');
            } else {
                Log::d('API.Restful._getQueryFromPath.Querying.NoID');

                $iPer   = isset($this->Request->GET['per'])  ? $this->Request->GET['per']  : 1000;
                $iPage  = isset($this->Request->GET['page']) ? $this->Request->GET['page'] : 1;
                $iStart = $iPer * ($iPage - 1);

                $oQuery->limit($iStart, $iPer);

                if (isset($this->Request->GET['search']) && strlen(trim($this->Request->GET['search']))) {
                    $aConditions = [];

                    $sSearch     = trim($this->Request->GET['search']);
                    $sSearchType = 'OR';

                    if (preg_match('/^(AND|OR)/', $sSearch, $aMatches)) {
                        $sSearchType = $aMatches[1];
                        $sSearch = trim(preg_replace('/^(AND|OR)/', '', $sSearch));
                    };

                    $sSearch     = preg_replace('/\s+/', ' ', $sSearch);
                    $sSearch     = preg_replace('/(\w+)\:"(\w+)/', '"${1}:${2}', $sSearch); // Make things like field:"Some Value" into "field: Some Value"
                    $aSearch     = str_getcsv($sSearch, ' ');

                    foreach($aSearch as $sSearchTerm) {
                        if (strpos($sSearchTerm, ':') !== false) {
                            $aSearchTerm  = explode(':', $sSearchTerm);
                            $sSearchField = array_shift($aSearchTerm);
                            $sSearchValue = implode(':', $aSearchTerm);
                            $oSearchField = DataMap::getField($oTable, $sSearchField);

                            if ($oSearchField instanceof ORM\Field) {
                                Log::d('API.Restful._getQueryFromPath.Querying.Search', ['field' => $sSearchField, 'value' => $sSearchValue, 'operator' => ':']);

                                if ($sSearchValue == 'null') {
                                    $aConditions[] = SQL::nul($oSearchField);
                                } else if ($oSearchField instanceof ORM\Field\Number
                                       ||  $oSearchField instanceof ORM\Field\Enum
                                       ||  $oSearchField instanceof ORM\Field\Date) {
                                    $aConditions[] = SQL::eq($oSearchField, $sSearchValue);
                                } else {
                                    $aConditions[] = SQL::like($oSearchField, '%' . $sSearchValue . '%');
                                }

                                continue;
                            }
                        } else if (strpos($sSearchTerm, '>') !== false) {
                            // TODO: Obviously ridiculous.  we should be parsing this properly instead of repeating
                            $aSearchTerm  = explode('>', $sSearchTerm);
                            $sSearchField = array_shift($aSearchTerm);
                            $sSearchValue = implode('>', $aSearchTerm);
                            $oSearchField = DataMap::getField($oTable, $sSearchField);

                            if ($oSearchField instanceof ORM\Field) {
                                Log::d('API.Restful._getQueryFromPath.Querying.Search', [
                                    'field'    => $oSearchField->sColumn,
                                    'type'     => get_class($oSearchField),
                                    'value'    => $sSearchValue,
                                    'operator' => '>'
                                ]);

                                if ($oSearchField instanceof ORM\Field\Number
                                ||  $oSearchField instanceof ORM\Field\Date) {
                                    Log::d('API.Restful._getQueryFromPath.Querying.Search.Number');
                                    $aConditions[] = SQL::gt($oSearchField, $sSearchValue);
                                }

                                continue;
                            }

                        }

                        // Search all Searchable fields - we should be checking if this is a general search (no colons or >'s or anything) and then only do this in that case
                        foreach ($oTable->getFields() as $oField) {
                            if ($oField instanceof ORM\Field\Date) {
                                // TODO: handle dates
                            } else if ($oField instanceof ORM\Field\Text) {
                                $aConditions[] = SQL::like($oField, '%' . $sSearchTerm . '%');
                            }
                        }
                    }

                    if ($sSearchType == 'AND') {
                        $oQuery->also(...$aConditions);
                    } else {
                        $oQuery->either(...$aConditions);
                    }
                }

                if (isset($this->Request->GET['sort']) && strlen(trim($this->Request->GET['sort']))) {
                    $sGetSort = trim($this->Request->GET['sort']);
                    $sGetSort = preg_replace('/,\s+/', ',', $sGetSort);
                    $aSort    = explode(',', $sGetSort);

                    foreach($aSort as $sSort) {
                        if (strpos($sSort, '.')) {
                            $aSort = explode('.', $sSort);
                            if (count($aSort) == 2) {
                                $sSortTable = DataMap::getClassName($aSort[0]);
                                $sSortField = $aSort[1];

                                Log::d('API.Restful._getQueryFromPath.Querying.NoID.ForeignSort', ['table' => $sSortTable, 'field' => $sSortField]);

                                $sSortTableClass = self::_getNamespacedTableClassName($sSortTable);

                                /** @var ORM\Table $oSortTable */
                                $oSortTable = new $sSortTableClass();
                                if (!$oSortTable instanceof ORM\Table) {
                                    throw new Exception\InvalidTable($sSortTableClass . " is not a valid Table");
                                }

                                $oSortReference = $oSortTable->getFieldThatReferencesTable($oTable);
                                if ($oSortReference instanceof ORM\Field !== false) {
                                    // The SortBy Field is in a table that references our Primary Table
                                    // Join from the Referenced Primary Table Field to the Sort Table Referencing Field
                                    $sReferenceField = $oSortReference->referenceField();
                                    $oQuery->join($oTable->$sReferenceField, $oSortReference);
                                } else {
                                    $oSortReference = $oTable->getFieldThatReferencesTable($oSortTable);

                                    if ($oSortReference instanceof ORM\Field === false) {
                                        throw new Exception\InvalidReference("Cannot Associate " . (new \ReflectionClass($oTable))->getShortName() . ' with ' . (new \ReflectionClass($oSortReference))->getShortName());
                                    }

                                    // The SortBy Field is in a table that our Primary Table references
                                    // Join from the Referencing Primary Table Field to the Referenced Sort Table Field Base Table Field
                                    $sReferenceField = $oSortReference->referenceField();
                                    $oQuery->join($oSortReference, $oSortTable->$sReferenceField);
                                }



                                $oSortField = DataMap::getField($oSortTable, $sSortField);
                                $oQuery->asc($oSortField);
                            }
                        } else {
                            $oSortField = DataMap::getField($oTable, $sSort);
                            if ($oSortField instanceof ORM\Field) {
                                $oQuery->asc($oSortField);
                            }
                        }
                    }
                }

                if (isset($this->Request->GET['sync'])) {
                    if ($oTable instanceof ORM\ModifiedDateColumn) {
                        $oQuery->also(
                            SQL::gte($oTable->getModifiedDateField(), $this->Request->GET['sync'])
                        );
                    }
                }
            }

            return $oQuery;
        }

        /**
         * @param string $sTableClass
         * @return string
         * @throws Exception\Response
         */
        private static function _getNamespacedTableClassName(string $sTableClass) {
            if (self::$sNamespaceTable === null) {
                throw new Exception\Response('API Route Not Initialized');
            }

            return implode('\\', [self::$sNamespaceTable, $sTableClass]);
        }

        /**
         * @return ORM\Table
         * @throws Exception\InvalidTable
         */
        private function _getPrimaryTableFromPath() {
            $aPairs = $this->Request->getPathPairs();

            if (count($aPairs) > 0) {
                $aLastPair  = array_pop($aPairs);
                $sClassName = DataMap::getClassName($aLastPair[0]);
                if (!$sClassName) {
                    throw new Exception\InvalidTable("Never Heard of " . $aLastPair[0]);
                }

                $sClass = self::_getNamespacedTableClassName($sClassName);

                /** @var ORM\Table $oTable */
                $oTable = new $sClass;
                if ($oTable instanceof ORM\Table === false) {
                    throw new Exception\InvalidTable('Invalid Primary Table in Path'); // DataMap is Wrong?!
                }

                return $oTable;
            }
        }
    }
