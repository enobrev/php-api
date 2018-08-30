<?php
    namespace Enobrev\API;

    use function Enobrev\dbg;
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
        protected $Data = null;

        /** @var  string */
        protected $sDataPath = null;

        /** @var string */
        protected $sBaseTable;

        /**
         * @param string $sBaseTable
         */
        public function setBaseTable(string $sBaseTable) {
            $this->sBaseTable = $sBaseTable;
        }

        /**
         * @return string
         */
        public function getDataPath() {
            return $this->sDataPath;
        }

        /**
         * @param string $sNamespaceTable
         */
        public static function init(string $sNamespaceTable): void {
            self::$sNamespaceTable = trim($sNamespaceTable, '\\');
        }

        /**
         * Set the Data upon Which the Response is built
         * @param ORM\Table|ORM\Tables $oData
         * @throws \Exception
         */
        public function setData($oData): void {
            if ($oData instanceof ORM\Table) {
                $this->sDataPath = $oData->getTitle();
            } else if ($oData instanceof ORM\Tables) {
                $this->sDataPath = $oData->getTable()->getTitle();
            }

            $this->Data  = $oData;
        }

        /**
         * @return bool
         */
        public function hasData(): bool {
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
        public function head(): void {
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
         * @psalm-suppress PossiblyInvalidArgument
         */
        protected function heads(): void {
            $this->Response->setLastModifiedFromTables($this->Data);
            $this->Response->statusNoContent();
            return;
        }

        /**
         * HTTP OPTIONS
         * @throws Exception\NoContentType
         */
        public function options(): void {
            $this->Response->respondWithOptions( ...Method\_ALL);
        }

        /**
         * HTTP GET
         */
        public function get(): void {
            if ($this->Data instanceof ORM\Tables) {
                $this->gets();
                return;
            }

            if ($this->Data instanceof ORM\Table === false) {
                $this->Response->statusNotFound();
                return;
            }

            $this->Response->add($this->getDataPath(), DataMap::getIndexedResponseMap($this->getDataPath(), $this->Data, $this->_getUrlKeyField($this->Data)->sColumn));
            $this->Response->add('counts.' . $this->getDataPath(), 1);
            $this->Response->add('sorts.' .  $this->getDataPath(), [$this->_getUrlKeyField($this->Data)->getValue()]);
            $this->Response->setHeadersFromTable($this->Data);
            return;
        }

        /**
         * HTTP GET for Multiple Records
         * @psalm-suppress PossiblyInvalidMethodCall
         * @psalm-suppress PossiblyUndefinedMethod
         * @psalm-suppress PossiblyInvalidArgument
         */
        protected function gets(): void {
            if ($this->Data->count() > 0) {
                $this->Response->add($this->getDataPath(), DataMap::getIndexedResponseMaps($this->getDataPath(), $this->Data, $this->_getUrlKeyField($this->Data[0])->sColumn));
                $this->Response->add('sorts.' . $this->getDataPath(), $this->getSorts());
                $this->Response->setLastModifiedFromTables($this->Data);
            }
            return;
        }

        /**
         * @return array
         * @psalm-suppress RawObjectIteration
         */
        protected function getSorts(): array {
            $aSorts = [];
            /** @var ORM\Table $oTable */
            foreach($this->Data as $oTable) {
                $aSorts[] = $this->_getUrlKeyField($oTable)->getValue();
            }

            return $aSorts;
        }

        /**
         * HTTP POST
         */
        public function post(): void {
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
                'mapped'        => json_encode(DataMap::getResponseMap($this->getDataPath(), $this->Data)),
                'overrides'     => json_encode($aOverrides)
            ]);

            /** @var ORM\Table $oTable */
            $oTable = get_class($this->Data);
            $this->Data = $oTable::createAndUpdateFromMap(
                $this->Request->POST,
                DataMap::getResponseMap($this->getDataPath(), $this->Data),
                $aOverrides,
                $this->_getUrlKeyField(new $oTable)->sColumn
            );

            $this->get();
        }

        /**
         * HTTP PUT
         */
        public function put(): void {
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
                'mapped'     => json_encode(DataMap::getResponseMap($this->getDataPath(), $this->Data)),
                'overrides'  => json_encode($aOverridePrimaries)
            ]);

            /** @var ORM\Table $oTable */
            $oTable = get_class($this->Data);
            $this->Data = $oTable::createAndUpdateFromMap(
                $this->Request->PUT,
                DataMap::getResponseMap($this->getDataPath(), $this->Data),
                $aOverridePrimaries,
                $this->_getUrlKeyField(new $oTable)->sColumn
            );

            $this->get();
        }

        /**
         * Grabs Attributes from URI and overrides Fields in POST or PUT data
         * @param array $aOverridePrimaries
         * @return array
         */
        protected function overridePrimaries(array $aOverridePrimaries = []): array {
            $aAttributes = $this->Request->OriginalRequest->getAttributes();
            foreach($aAttributes as $sField => $sValue) {
                try {
                    $oField = $this->Data->$sField;
                    if ($oField instanceof ORM\Field) {
                        $aOverridePrimaries[$sField] = $sValue;
                    }
                } catch (\Exception $e) {
                    // Had a Problem grabbing a field - not the end of the world.
                }
            }

            return $aOverridePrimaries;
        }

        /**
         * HTTP DELETE
         */
        public function delete(): void {
            if ($this->Data instanceof ORM\Table === false) {
                $this->Response->statusNotFound();
                return;
            }

            $this->Data->delete();
            $this->Response->statusNoContent();
            return;
        }

        protected function handleFiles(): void {

        }

        /**
         * @throws Exception
         * @throws Exception\InvalidReference
         * @throws Exception\InvalidTable
         * @throws Exception\Response
         * @throws ORM\DbException
         * @throws ORM\TableException
         * @throws \Exception
         * @psalm-suppress ImplicitToStringCast
         */
        public function setDataFromPath(): void {
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
                            $this->Request->updatePathParam($oReference->sColumn, $oReference->getValue());
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
                                    /** @var int|bool $iCount */
                                    $iCount = $oResult->fetchColumn();
                                    if ($iCount !== false) {
                                        $this->Response->add('counts.' . $oTable->getTitle(), (int) $iCount);
                                    }
                                }
                            }
                        } else if ($this->Request->isPost()) {
                            Log::d('API.Restful._getResultsFromPath.FoundNone.Post');

                            $sKey = $this->_getUrlKeyField($oTable)->sColumn;
                            $oTable->$sKey->setValue($aLastPair[1]);

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
         * @param Table $oTable
         * @return ORM\Field
         */
        protected function _getUrlKeyField(Table $oTable) {
            return $oTable->getPrimary()[0];
        }

        /**
         * @throws Exception
         * @throws Exception\InvalidReference
         * @throws Exception\InvalidTable
         * @throws Exception\Response
         * @throws ORM\ConditionsNonConditionException
         * @return SQL|SQLBuilder|string
         * @psalm-suppress InvalidArgument
         */
        public function _getQueryFromPath() {
            $oTable    = $this->_getPrimaryTableFromPath();

            Log::d('API.Restful._getQueryFromPath', [
                'table_class'   => get_class($oTable),
                'table'         => $oTable->getTitle()
            ]);

            $oQuery = SQLBuilder::select($oTable);

            $oPrimaryField = $this->_getUrlKeyField($oTable);
            $aPairs        = $this->Request->getPathPairs();
            $aLastPair     = array_pop($aPairs);
            if (isset($aLastPair[1])) {
                $oQuery->eq_in($oPrimaryField, $aLastPair[1]);
                $this->Request->updatePathParam($oPrimaryField->sColumn, $aLastPair[1]);
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
                    $this->Request->updatePathParam($oReference->sColumn, $aPart[1]);

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
                    $sSearch     = preg_replace('/(\w+)([:><])"(\w+)/', '"${1}${2}${3}', $sSearch); // Make things like field:"Some Value" into "field: Some Value"
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
                            $aSplit = explode('.', $sSort);
                            if (count($aSplit) == 2) {
                                $sSortTable = DataMap::getClassName($aSplit[0]);
                                $sSortField = $aSplit[1];

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
                                    $oQuery->fields($oTable); // Setting Primary Table fields to ensure joined fields aren't the only ones returned
                                    $oQuery->join($oTable->$sReferenceField, $oSortReference);
                                } else {
                                    $oSortReference = $oTable->getFieldThatReferencesTable($oSortTable);

                                    if ($oSortReference instanceof ORM\Field === false) {
                                        throw new Exception\InvalidReference("Cannot Associate " . (new \ReflectionClass($oTable))->getShortName() . ' with ' . (new \ReflectionClass($oSortReference))->getShortName());
                                    }

                                    // The SortBy Field is in a table that our Primary Table references
                                    // Join from the Referencing Primary Table Field to the Referenced Sort Table Field Base Table Field
                                    $sReferenceField = $oSortReference->referenceField();
                                    $oQuery->fields($oTable); // Setting Primary Table fields to ensure joined fields aren't the only ones returned
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
        public static function _getNamespacedTableClassName(string $sTableClass): string {
            if (self::$sNamespaceTable === null) {
                throw new Exception\Response('Rest Class Not Initialized');
            }

            return implode('\\', [self::$sNamespaceTable, $sTableClass]);
        }

        /**
         * @return ORM\Table
         * @throws Exception\InvalidTable
         * @throws Exception\Response
         */
        public function _getPrimaryTableFromPath(): ORM\Table {
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

            throw new Exception\InvalidTable('Primary Table Pair Not Found in Path');
        }
    }
