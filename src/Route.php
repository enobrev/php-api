<?php
    namespace Enobrev\API;

    use PDO;
    use Enobrev\API\Exception;
    use Enobrev\Log;
    use Enobrev\ORM;
    use Enobrev\SQL;
    use Enobrev\SQLBuilder;
    use function Enobrev\dbg;

    use RecursiveIteratorIterator;
    use RecursiveDirectoryIterator;
    use FilesystemIterator;
    use SplFileInfo;

    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\ServerRequestFactory;

    use function Enobrev\array_is_multi;

    class Route {
        /** @var array */
        private static $aCachedRoutes = [];

        /** @var array */
        private static $aCachedQueryRoutes = [];

        /** @var bool */
        private static $bReturnResponses = false;

        /** @var array */
        private static $aVersions = ['v1'];

        /** @var string */
        private static $sPathAPI = null;

        /** @var string */
        private static $sNamespaceAPI = null;

        /** @var string */
        private static $sNamespaceTable = null;

        /** @var string */
        private static $sRestClass = Rest::class;

        /**
         * @param string $sPathAPI
         * @param string $sNamespaceAPI
         * @param string $sNamespaceTable
         * @param string $sRestClass
         * @param array  $aVersions
         */
        public static function init(string $sPathAPI, string $sNamespaceAPI, string $sNamespaceTable, string $sRestClass = Rest::class, array $aVersions = ['v1']) {
            self::$sPathAPI        = rtrim($sPathAPI, '/') . '/';
            self::$sNamespaceAPI   = trim($sNamespaceAPI, '\\');
            self::$sNamespaceTable = trim($sNamespaceTable, '\\');
            self::$aVersions       = $aVersions;
            self::$sRestClass      = $sRestClass;

            self::_generateRoutes();
        }

        /**
         * @param ServerRequest $oServerRequest
         * @return \stdClass|void
         */
        public static function index(ServerRequest $oServerRequest = null) {
            $bReturn        = self::$bReturnResponses; // Set this before _getResponse overrides it
            $oServerRequest = $oServerRequest ?? ServerRequestFactory::fromGlobals();
            $oRequest       = new Request($oServerRequest);
            $oResponse      = self::_getResponse($oRequest);

            if ($bReturn) {
                Log::d('Route.index.return');
                return $oResponse->getOutput();
            } else {
                Log::d('Route.index.respond');
                $oResponse->respond();
            }
        }

        const QUERY_ROUTE_ENDPOINT = 'ENDPOINT';
        const QUERY_ROUTE_TABLE    = 'TABLE';

        /**
         * @param string $sRoute
         * @param string $sClass
         * @param string $sMethod
         */
        public static function addEndpointRoute(string $sRoute, string $sClass, string $sMethod) {
            self::addQueryRoute(self::QUERY_ROUTE_ENDPOINT, $sRoute, $sClass, $sMethod);
        }

        /**
         * @param string $sRoute
         * @param string $sClass
         * @param string $sMethod
         */
        public static function addTableRoute(string $sRoute, string $sClass, string $sMethod) {
            self::addQueryRoute(self::QUERY_ROUTE_TABLE, $sRoute, $sClass, $sMethod);
        }

        /**
         * @param string $sType
         * @param string $sRoute
         * @param string $sClass
         * @param string $sMethod
         */
        private static function addQueryRoute(string $sType, string $sRoute, string $sClass, string $sMethod) {
            foreach (self::$aVersions as $sVersion) {
                $aRoute = [
                    'type'   => $sType,
                    'class'  => $sClass,
                    'method' => $sMethod
                ];

                $sVersionedRoute  = $sVersion . '/' . trim($sRoute, '/');
                $aPath            = explode('/', $sVersionedRoute);

                $aParsed = [];
                $aParams = [];
                foreach($aPath as $sSegment) {
                    if (strpos($sSegment, '{') === 0) {
                        $sMatch    = trim($sSegment, "{}");
                        $aParams[] = $sMatch;
                        $aParsed[] = '(?<' . $sMatch . '>[^/]+)';
                    } else {
                        $aParsed[] = $sSegment;
                    }
                }

                $sVersionedRoute = implode('/', $aParsed);
                $sVersionedRoute = str_replace('*', '([^/]+)', $sVersionedRoute);
                $sVersionedRoute = '~' . $sVersionedRoute . '~';

                $aRoute['segments'] = count($aPath);
                $aRoute['params']   = $aParams;

                self::$aCachedQueryRoutes[$sVersionedRoute] = $aRoute;
            }
        }

        /**
         * @param string $sVersion
         * @return bool
         */
        public static function isVersion(string $sVersion) {
            return in_array($sVersion, self::$aVersions);
        }

        /**
         * @return string
         */
        public static function defaultVersion() {
            return self::$aVersions[0];
        }

        /**
         * @param Request $oRequest
         * @return Response
         */
        public static function _getResponse(Request $oRequest) {
            if (!self::$bReturnResponses && $oRequest->pathIsRoot() && !$oRequest->isOptions()) {
                if ($oResponse = self::_attemptMultiRequest($oRequest)) {
                    return $oResponse;
                }
            }

            $aRoute   = self::_matchRoute(self::$aCachedRoutes, $oRequest);
            if ($aRoute) {
                return self::_endpoint($aRoute, $oRequest);
            }

            try {
                $oRest   = self::_getRestClass($oRequest);

                Log::d('Route.query.rest', [
                    'class' => get_class($oRest)
                ]);

                if ($oRequest->isOptions()) {
                    $oRest->options();
                    return $oRest->Response;
                }

                $sMethod = strtolower($oRequest->OriginalRequest->getMethod());
                if (method_exists($oRest, $sMethod)) {

                    /** @var ORM\Tables|ORM\Table $oResults */
                    $aRoute = self::_matchQuery(self::$aCachedQueryRoutes, $oRequest);
                    if ($aRoute) {
                        $sClass       = $aRoute['class'];
                        $sQueryMethod = $aRoute['method'];

                        Log::d('Route.query.cached', [
                            'class'         => $sClass,
                            'method'        => $sQueryMethod,
                            'path'          => $oRequest->Path,
                            'headers'       => $oRequest->OriginalRequest->getHeaders(),
                            'attributes'    => $oRequest->OriginalRequest->getAttributes()
                        ]);

                        if (method_exists($sClass, $sQueryMethod)) {
                            // If Class is a Table, then use Rest and setData from that Method, otherwise, just run the Method
                            switch($aRoute['type']) {
                                case self::QUERY_ROUTE_TABLE:
                                    $oResults = $sClass::$sQueryMethod($aRoute['params']);

                                    if ($oResults) {
                                        $oRest->setData($oResults);
                                    }

                                    $oRest->$sMethod();
                                    break;

                                case self::QUERY_ROUTE_ENDPOINT:
                                    $oRequest->updateParams($aRoute['params']);

                                    /** @var Base $oClass */
                                    $oClass = new $sClass($oRequest);
                                    $oClass->$sQueryMethod();
                                    return $oClass->Response;
                                    break;
                            }
                        } else {
                            $oRest->Response->setStatus(HTTP\SERVICE_UNAVAILABLE);
                            $oRest->Response->setFormat(Response::FORMAT_EMPTY);
                        }
                    } else {
                        self::_setRestDataFromPath($oRest, $oRequest);

                        Log::d('Route.query.dynamic', [
                            'path'          => $oRequest->Path,
                            'method'        => $sMethod,
                            'headers'       => $oRequest->OriginalRequest->getHeaders(),
                            'attributes'    => $oRequest->OriginalRequest->getAttributes(),
                            'query'         => $oRequest->GET
                        ]);

                        if (!$oRest->hasData() && $oRequest->isPut()) { // Tried to PUT with an ID and no record was found
                            $oRest->Response->statusNotFound();
                        } else {
                            $oRest->$sMethod();
                        }
                    }

                    return $oRest->Response;
                }

                $oRest->Response->statusMethodNotAllowed();
                return $oRest->Response;
            } catch (\Exception $e) {
                Log::c('API.REQUEST.FAIL', [
                    'request' => [
                        'path'      => $oRequest->OriginalRequest->getUri()->getPath(),
                        'headers'   => $oRequest->OriginalRequest->getHeaders(),
                        'params'    => $oRequest->OriginalRequest->getParsedBody()
                    ],
                    'error' => [
                        'type'    => get_class($e),
                        'message' => $e->getMessage(),
                        'stack'   => $e->getTrace()
                    ]
                ]);

                $oResponse = new Response($oRequest);
                $oResponse->setStatus(HTTP\SERVICE_UNAVAILABLE);
                $oResponse->setFormat(Response::FORMAT_EMPTY);
                return $oResponse;
            }
        }

        /**
         * move down the path from right to left until we find the segment that represents a table
         * @param Request $oRequest
         * @return RestfulInterface
         */
        public static function _getRestClass(Request $oRequest) {
            if (count($oRequest->Path) > 1) {
                $aPath         = $oRequest->Path;
                $sTopClass     = null;

                while(count($aPath) > 0 && $sTopClass === null) {
                    $sTopMost = array_pop($aPath);
                    if (DataMap::hasClassPath($sTopMost)) {
                        $sTopClass = DataMap::getClassName($sTopMost);
                    }
                }

                if ($sTopClass) {
                    $sRestClass = self::_getNamespacedAPIClassName($oRequest->Path[0], $sTopClass);
                    if (class_exists($sRestClass)) {
                        return new $sRestClass($oRequest);
                    }
                }
            }

            return new self::$sRestClass($oRequest);
        }

        /**
         * @param string $sVersionPath
         * @param string $sAPIClass
         * @return string
         * @throws Exception\Response
         */
        public static function _getNamespacedAPIClassName(string $sVersionPath, string $sAPIClass) {
            if (self::$sNamespaceAPI === null) {
                throw new Exception\Response('API Route Not Initialized');
            }

            return implode('\\', [self::$sNamespaceAPI, $sVersionPath, $sAPIClass]);
        }

        /**
         * @param string $sTableClass
         * @return string
         * @throws Exception\Response
         */
        public static function _getNamespacedTableClassName(string $sTableClass) {
            if (self::$sNamespaceTable === null) {
                throw new Exception\Response('API Route Not Initialized');
            }

            return implode('\\', [self::$sNamespaceTable, $sTableClass]);
        }

        /**
         * @param Request $oRequest
         * @return ORM\Table
         * @throws Exception\InvalidTable
         */
        public static function _getPrimaryTableFromPath(Request $oRequest) {
            $aPairs = $oRequest->getPathPairs();

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

        /**
         * @param Request           $oRequest
         * @param RestfulInterface  $oRest
         * @throws Exception\InvalidReference
         * @throws Exception\InvalidTable
         * @throws \Enobrev\API\Exception
         */
        public static function _setRestDataFromPath(RestfulInterface $oRest, Request &$oRequest) {
            $aPairs = $oRequest->getPathPairs();

            if (count($aPairs) > 0) {
                $aLastPair = array_pop($aPairs);
                $bHasClass = DataMap::getClassName($aLastPair[0]) !== null;

                if ($oRequest->isPost() && !isset($aLastPair[1])) {
                    Log::d('Route._getResultsFromPath.Post.NoId');

                    $oTable = self::_getPrimaryTableFromPath($oRequest);

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
                            $oRequest->updateParam($oReference->sColumn, $oReference->getValue());
                        }
                    }

                    $oRest->setData($oTable);
                } else if ($bHasClass) {
                    $oQuery = self::_getQueryFromPath($oRequest);

                    $oDb = ORM\Db::getInstance();
                    if ($oResults = $oDb->namedQuery('getQueryFromPath', $oQuery)) {
                        $iRows  = $oDb->getLastRowsAffected();
                        $oTable = self::_getPrimaryTableFromPath($oRequest);

                        if ($iRows == 1) {
                            Log::d('Route._getResultsFromPath.FoundOne');

                            $oRest->setData($oResults->fetchObject(get_class($oTable)));
                        } else if ($iRows > 1) {
                            Log::d('Route._getResultsFromPath.FoundMultiple');

                            $oTables = $oTable::getTables();
                            $oRest->setData(new $oTables($oResults->fetchAll(PDO::FETCH_CLASS, get_class($oTable))));

                            // Add the count to the dynamic query output
                            if ($oQuery instanceof SQLBuilder) {
                                $oQuery->setType(SQLBuilder::TYPE_COUNT);

                                if ($oResult = ORM\Db::getInstance()->namedQuery('getCountQueryFromPath', $oQuery)) {
                                    $iCount = $oResult->fetchColumn();
                                    if ($iCount !== false) {
                                        $oRest->Response->add('counts.' . $oTable->getTitle(), (int) $iCount);
                                    }
                                }
                            }
                        } else if ($oRequest->isPost()) {
                            Log::d('Route._getResultsFromPath.FoundNone.Post');

                            $aPrimary = $oTable->getPrimaryFieldNames();
                            if (count($aPrimary) == 1) {
                                $sPrimary = array_shift($aPrimary);
                                $oTable->$sPrimary->setValue($aLastPair[1]);
                            }

                            $oRest->setData($oTable);
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
         * @param Request   $oRequest
         * @return SQL|SQLBuilder|string
         * @throws Exception\InvalidReference
         * @throws Exception\InvalidTable
         * @throws \Enobrev\API\Exception
         */
        public static function _getQueryFromPath(Request &$oRequest) {
            $oTable    = self::_getPrimaryTableFromPath($oRequest);

            Log::d('Route._getQueryFromPath', [
                'table_class'   => get_class($oTable),
                'table'         => $oTable->getTitle()
            ]);

            $oQuery = SQLBuilder::select($oTable);

            $aPairs    = $oRequest->getPathPairs();
            $aLastPair = array_pop($aPairs);
            if (isset($aLastPair[1])) {
                $oQuery->eq_in($oTable->getPrimary()[0], $aLastPair[1]);
                $oRequest->updateParam($oTable->getPrimary()[0]->sColumn, $aLastPair[1]);
            }

            while (count($aPairs) > 0) {
                Log::d('Route._getQueryFromPath.Pairs', $aPairs);

                $aPart = array_shift($aPairs);
                $sClassName = DataMap::getClassName($aPart[0]);
                $sClass = self::_getNamespacedTableClassName($sClassName);

                /** @var ORM\Table $oWhereTable */
                $oWhereTable = new $sClass();

                if ($oWhereTable instanceof ORM\Table === false) {
                    throw new Exception('Invalid Where Table in Query Path');
                }

                if (isset($aPart[1])) {
                    Log::d('Route._getQueryFromPath.Pairs.PartWithValue', $aPart);
                    $oReference = $oTable->getFieldThatReferencesTable($oWhereTable);
                    if ($oReference instanceof ORM\Field === false) {
                        throw new Exception\InvalidReference("Cannot Associate " . (new \ReflectionClass($oTable))->getShortName() . ' with ' . (new \ReflectionClass($oWhereTable))->getShortName());
                    }

                    $oQuery->eq_in($oReference, $aPart[1]);
                    $oRequest->updateParam($oReference->sColumn, $aPart[1]);

                    Log::d('Route._getQueryFromPath.Pairs.AddingAttribute', [
                        'field' => $oReference->sColumn,
                        'value' => $aPart[1]
                    ]);
                }
            }

            if (isset($aLastPair[1])) {
                Log::d('Route._getQueryFromPath.Querying.HasID');
            } else {
                Log::d('Route._getQueryFromPath.Querying.NoID');

                $iPer   = isset($oRequest->GET['per'])  ? $oRequest->GET['per']  : 1000;
                $iPage  = isset($oRequest->GET['page']) ? $oRequest->GET['page'] : 1;
                $iStart = $iPer * ($iPage - 1);

                $oQuery->limit($iStart, $iPer);

                if (isset($oRequest->GET['search']) && strlen(trim($oRequest->GET['search']))) {
                    $aConditions = [];

                    $sSearch     = trim($oRequest->GET['search']);
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
                                Log::d('Route._getQueryFromPath.Querying.Search', ['field' => $sSearchField, 'value' => $sSearchValue, 'operator' => ':']);

                                if ($sSearchValue == 'null') {
                                    $aConditions[] = SQL::nul($oSearchField);
                                } else if ($oSearchField instanceof ORM\Field\Number
                                       ||  $oSearchField instanceof ORM\Field\Enum) {
                                    $aConditions[] = SQL::eq($oSearchField, $sSearchValue);
                                } else if ($oSearchField instanceof ORM\Field\Date) {
                                    // TODO: handle dates
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
                                Log::d('Route._getQueryFromPath.Querying.Search', ['field' => $sSearchField, 'value' => $sSearchValue, 'operator' => '>']);

                                if ($oSearchField instanceof ORM\Field\Number) {
                                    $aConditions[] = SQL::gt($oSearchField, $sSearchValue);
                                } else if ($oSearchField instanceof ORM\Field\Date) {
                                    // TODO: handle dates
                                }

                                continue;
                            }

                        }

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

                if (isset($oRequest->GET['sort']) && strlen(trim($oRequest->GET['sort']))) {
                    $sGetSort = trim($oRequest->GET['sort']);
                    $sGetSort = preg_replace('/,\s+/', ',', $sGetSort);
                    $aSort    = explode(',', $sGetSort);

                    foreach($aSort as $sSort) {
                        if (strpos($sSort, '.')) {
                            $aSort = explode('.', $sSort);
                            if (count($aSort) == 2) {
                                $sSortTable = DataMap::getClassName($aSort[0]);
                                $sSortField = $aSort[1];

                                Log::d('Route._getQueryFromPath.Querying.NoID.ForeignSort', ['table' => $sSortTable, 'field' => $sSortField]);

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
            }

            return $oQuery;
        }

        /**
         * @param array   $aRoute
         * @param Request $oRequest
         * @return Response|void
         */
        public static function _endpoint(Array $aRoute, Request $oRequest) {
            Log::d('Route.endpoint', [
                'class'         => $aRoute['class'],
                'path'          => $oRequest->Path,
                'headers'       => $oRequest->OriginalRequest->getHeaders(),
                'attributes'    => $oRequest->OriginalRequest->getAttributes()
            ]);

            /** @var Base $oClass */
            $oClass  = new $aRoute['class']($oRequest);
            $sMethod = strtolower($oRequest->OriginalRequest->getMethod());

            if ($oClass instanceof Base) {
                if (isset($aRoute['method'])) {
                    $sMethod = $aRoute['method'];
                }

                if (method_exists($oClass, $sMethod)) {
                    Log::d('Route.endpoint.response');
                    $oClass->$sMethod();
                } else {
                    Log::w('Route.endpoint.methodNotFound');
                    $oClass->methodNotAllowed();
                }

                return $oClass->Response;
            } else {
                // FIXME: return error;
                Log::w('Route.endpoint.ClassNotFound');
            }

            return;
        }

        /**
         * @param array $aRoutes
         * @param Request $oRequest
         * @return array|bool
         */
        public static function _matchRoute(Array $aRoutes, Request $oRequest) {
            $sRoute = implode('/', $oRequest->Path);
            $sRoute = trim($sRoute, '/');

            if (isset($aRoutes[$sRoute])) {
                return $aRoutes[$sRoute];
            }

            return false;
        }

        /**
         * @param array $aRoutes
         * @param Request $oRequest
         * @return array|bool
         */
        public static function _matchQuery(Array $aRoutes, Request $oRequest) {
            $iSegments = count($oRequest->Path);
            $sRoute = implode('/', $oRequest->Path);
            $sRoute = trim($sRoute, '/');

            $aPossible = array_filter($aRoutes, function ($aRoute) use ($iSegments) {
                return $aRoute['segments'] == $iSegments;
            });

            foreach ($aPossible as $sMatch => $aRoute) {
                $aMatches = [];
                if (preg_match($sMatch, $sRoute, $aMatches)) {
                    if (isset($aRoute['options']) && is_array($aRoute['options']) && count($aRoute['options']) > 0) {
                        if (!in_array(strtoupper($oRequest->Method), $aRoute['options'])) {
                            continue;
                        }
                    }

                    $aRoute['params'] = array_intersect_key($aMatches, array_flip($aRoute['params']));
                    return $aRoute;
                }
            }

            return false;
        }

        /**
         * Traverse the API path, find all the version folders, and add a Route for each Public Method in all Classes that extend API\Base
         * Ideally we would have a script that would write all these routes to a cached file during the build process
         * and then the routes would be loaded immediately for production systems
         *
         * @todo Cache These for Production
         */
        public static function _generateRoutes() {
            foreach (self::$aVersions as $sVersion) {
                // TODO: Collect all paths from previous version that are not now private / protected, and copy them to the current version
                // TODO: for instance, if we have v1/class/methoda, and v2 doesn't override it, then we should have v2/class/methoda which points to v1/class/methoda

                $sVersionPath = self::$sPathAPI . $sVersion . '/';
                if (file_exists($sVersionPath)) {
                    /** @var SplFileInfo[] $aFiles */
                    $aFiles  = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator(
                            $sVersionPath,
                            FilesystemIterator::SKIP_DOTS
                        )
                    );

                    foreach($aFiles as $oFile) {
                        $sFile = file_get_contents($oFile->getPathname());
                        if (preg_match('/class\s+([^\s]+)\s+extends\s+(((\\\)?Enobrev\\\)?API\\\)?Base/', $sFile, $aMatches)) {
                            $aPublicMethods     = [];
                            $sClass             = $aMatches[1];
                            $sClassPath         = self::_getNamespacedAPIClassName($sVersion, $sClass);
                            $oReflectionClass   = new \ReflectionClass($sClassPath);
                            $aReflectionMethods = $oReflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);

                            foreach($aReflectionMethods as $oReflectionMethod) {
                                if ($oReflectionMethod->class == $oReflectionClass->getName()) {
                                    $aPublicMethods[] = $oReflectionMethod->name;
                                }
                            }

                            foreach($aPublicMethods as $sMethod) {
                                if ($sMethod == 'index') {
                                    $sRoute = implode('/', [$sVersion, strtolower($sClass)]);
                                } else {
                                    $sRoute = implode('/', [$sVersion, strtolower($sClass), $sMethod]);
                                }

                                self::$aCachedRoutes[$sRoute] = [
                                    'class'  => $sClassPath,
                                    'method' => $sMethod
                                ];


                            }
                        }
                    }
                }
            }
        }

        /**
         * @return array
         */
        public static function _getCachedRoutes() {
            return self::$aCachedRoutes;
        }

        public static function _getCachedQueryRoutes() {
            return self::$aCachedQueryRoutes;
        }

        private static $aData = [];

        /**
         * __query can be an array of endpoints, which will become an array of GET requests
         * __query can be a multi-array, keyed by endpoints with POST values as data, which will become an array of POST requests
         * the endpoint data can be an array of endpoint data which becomes multiple POST requests to the same endpoint
         * The endpoints can be templated with the results from previous queries.  Some examples:
         *
         * Query All Cities and All City Fonts with the resulting city ids
         * __query: [
         *     /cities/
         *     /city_fonts/{cities.id}
         * ]
         *
         * POST to cities and users
         * __query: {
         *     '/cities/': {
         *         name: 'New City'
         *     },
         *     '/users/1: {
         *         name: 'My New Name'
         *     }
         * }
         *
         * CREATE new city and related city fonts
         * __query: {
            'cities/': {
                name: 'New City'
            },
            'city_fonts/': [
                {
                    city_id: '{cities.id}',
                    font_placement: 'Header'
                },
                {
                    city_id: '{cities.id}',
                    font_placement: 'Subheader'
                },
                {
                    city_id: '{cities.id}',
                    font_placement: 'Title'
                },
                {
                    city_id: '{cities.id}',
                    font_placement: 'Tip'
                }
            ]
        }
         *
         *
         *
         * @param Request $oRequest
         * @return Response|void
         */
        public static function _attemptMultiRequest(Request $oRequest) {
            if (self::$bReturnResponses) {
                return;
            }

            Log::d('Route.attemptMultiRequest', [
                'path'          => $oRequest->Path,
                'headers'       => $oRequest->OriginalRequest->getHeaders(),
                'attributes'    => $oRequest->OriginalRequest->getAttributes()
            ]);

            self::$bReturnResponses = true;

            self::$aData['__requests'] = [];
            if (isset($oRequest->POST['__query'])) {
                $aQuery = is_array($oRequest->POST['__query']) ? $oRequest->POST['__query'] : json_decode($oRequest->POST['__query']);

                if (array_is_multi($aQuery)) {
                    foreach($aQuery as $sEndpoint => $aPost) {
                        if (array_is_multi($aPost)) {
                            foreach($aPost as $aEach) {
                                self::_attemptRequest($sEndpoint, $aEach);
                            }
                        } else {
                            self::_attemptRequest($sEndpoint, $aPost);
                        }
                    }
                } else {
                    while (count($aQuery) > 0) {
                        self::_attemptRequest(array_shift($aQuery));
                    }
                }

                $oResponse = new Response($oRequest);
                $oResponse->add(self::$aData);

                return $oResponse;
            }
        }

        /**
         * @param string $sEndpoint
         * @param array $aPostParams
         */
        public static function _attemptRequest($sEndpoint, array $aPostParams = []) {
            try {
                $sEndpoint   = self::_fillEndpointTemplateFromData($sEndpoint);
                $aPostParams = self::_fillPostTemplateFromData($aPostParams);
            } catch (Exception\NoTemplateValues $e) {
                Log::e('API.attemptRequest.skipped.missing.keys', [
                    'endpoint' => $sEndpoint,
                    'params'   => $aPostParams
                ]);

                return;
            }

            $iPosition   = strpos($sEndpoint, '?');

            $aServer = $_SERVER;
            $aServer['QUERY_STRING']   = $iPosition !== false ? substr($sEndpoint, $iPosition) : '';
            $aServer['REQUEST_URI']    = $sEndpoint;
            $aServer['ORIG_PATH_INFO'] = $sEndpoint;
            $aServer['REQUEST_METHOD'] = count($aPostParams) > 0 ? 'POST' : 'GET';

            $aGet = [];
            if (strlen($aServer['QUERY_STRING']) > 0) {
                parse_str(substr($aServer['QUERY_STRING'], 1), $aGet);
            }

            Log::startChildRequest();
            $oResponse = self::index(ServerRequestFactory::fromGlobals($aServer, $aGet, $aPostParams));
            Log::endChildRequest();

            if ($oResponse && $oResponse->status == HTTP\OK) { //  || $oResponse->status == HTTP\NOT_FOUND // Return the 0-count
                Log::d('API.ENDPOINT.RESPONSE', array(
                    'status'  => $oResponse->status,
                    'headers' => $oResponse->headers,
                    'body'    => $oResponse->data
                ));

                $aResponseParsed = json_decode(json_encode($oResponse->data), true); // FIXME: Inefficient and silly object to array conversion

                self::$aData['__requests'][] = $aResponseParsed['_request'];
                unset($aResponseParsed['_request']);
                unset($aResponseParsed['_server']);

                if (isset($aResponseParsed['counts'])) {
                    if (!isset(self::$aData['counts'])) {
                        self::$aData['counts'] = [];
                    }

                    foreach($aResponseParsed['counts'] as $sPath => $iCount) {
                        if (!isset(self::$aData['counts'][$sPath])) {
                            self::$aData['counts'][$sPath] = 0;
                        }

                        self::$aData['counts'][$sPath] += $iCount;
                    }

                    unset($aResponseParsed['counts']);
                }

                foreach($aResponseParsed as $sTable => $aRecords) {
                    if (!isset(self::$aData[$sTable])) {
                        self::$aData[$sTable] = [];
                    }

                    foreach($aRecords as $sId => $aRecord) {
                        self::$aData[$sTable][$sId] = $aRecord;
                    }
                }
            } else if ($oResponse) {
                // TODO: Report Errors

                Log::e('API.ENDPOINT.RESPONSE', json_decode(json_encode($oResponse), true)); // FIXME: Inefficient and silly object to array conversion
            } else {
                Log::e('API.ENDPOINT.RESPONSE.NONE');
            }
        }

        /**
         * Turns something like /city_fonts/{cities.id} into /city_fonts/1 using results of previously called API endpoints
         *
         * @param string $sEndpoint
         * @return string
         * @throws \Exception
         */
        public static function _fillEndpointTemplateFromData($sEndpoint) {
            $aEndpoint  = explode('/', $sEndpoint);
            foreach($aEndpoint as $sSegment) {
                $mTemplateValue = self::_getTemplateValue($sSegment);
                if ($mTemplateValue !== self::NO_VALUE) {
                    $sEndpoint = str_replace($sSegment, $mTemplateValue, $sEndpoint);
                }
            }

            return $sEndpoint;
        }

        /**
         * Turns something like {city_id: {cities.id}} into {city_id: 1} using results of previously called API endpoints
         *
         * @param array $aPost
         * @return array
         * @throws \Exception
         */
        public static function _fillPostTemplateFromData(array $aPost) {
            if (count($aPost)) {
                foreach($aPost as $sParam => $mValue) {
                    $mTemplateValue = self::_getTemplateValue($mValue);
                    if ($mTemplateValue !== self::NO_VALUE) {
                        $aPost[$sParam] = $mTemplateValue;
                    }
                }
            }

            return $aPost;
        }

        const NO_VALUE = 'NO_VALUE';

        public static function _getTemplateValue($sTemplate) {
            if (strpos($sTemplate, '{') === 0) {
                $sMatch = trim($sTemplate, "{}");
                $aMatch = explode('.', $sMatch);
                if (count($aMatch) == 2) {
                    $sTable = $aMatch[0];
                    $sField = $aMatch[1];

                    if (isset(self::$aData[$sTable])) {
                        $aValues = [];
                        foreach (self::$aData[$sTable] as $aTable) {
                            if (is_array($aTable) && array_key_exists($sField, $aTable)) {
                                $aValues[] = $aTable[$sField];
                            } else if (is_array(self::$aData[$sTable]) && isset(self::$aData[$sTable][$sField])) { // Single-Record response (like /me)
                                $aValues[] = self::$aData[$sTable][$sField];
                            } else {
                                throw new Exception\InvalidSegmentVariable('Invalid Segment Variable ' . $sField . ' in ' . $sTemplate);
                            }
                        }

                        Log::d('Route.getTemplateValue', [
                            'template' => $sTemplate,
                            'values'   => $aValues
                        ]);

                        $aUniqueValues = array_unique(array_filter($aValues));
                        if (count($aValues) > 0 && count($aUniqueValues) == 0) {
                            throw new Exception\NoTemplateValues();
                        }

                        return implode(',', $aUniqueValues);
                    }
                }
            }

            return $sTemplate;
        }
    }
