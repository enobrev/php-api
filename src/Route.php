<?php
    namespace Enobrev\API;

    use Enobrev\API\Exception;
    use Enobrev\Log;
    use Enobrev\ORM;
    use Enobrev\SQL;
    use Enobrev\SQLBuilder;
    use Enobrev\NoticeException;
    use Zend\Diactoros\Response\EmptyResponse;
    use Zend\Diactoros\Response\SapiEmitter;
    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\ServerRequestFactory;

    use function Enobrev\array_is_multi;

    class Route {
        const VERSIONS = ['v1'];
        const ROUTES = [];
        const QUERIES = [];

        /** @var array  */
        private static $aCachedRoutes = [];

        /** @var array  */
        private static $aCachedQueryRoutes = [];

        /** @var SQL|SQLBuilder|string */
        private static $oPathQuery;

        /** @var bool */
        private static $bReturnResponses = false;

        /**
         * @param ServerRequest $oServerRequest
         * @return \stdClass|void
         */
        public static function index(ServerRequest $oServerRequest = null) {
            $oServerRequest = $oServerRequest ?? ServerRequestFactory::fromGlobals();
            $oRequest = new Request($oServerRequest);
            $oResponse = self::getResponse($oRequest);

            if (self::$bReturnResponses) {
                return $oResponse->getOutput();
            } else {
                $oResponse->respond();
            }
        }

        /**
         * @param Request $oRequest
         * @return Response
         */
        private static function getResponse(Request $oRequest) {
            if (!self::$bReturnResponses && $oRequest->pathIsRoot() && !$oRequest->isOptions()) {
                if ($oResponse = self::attemptMultiRequest($oRequest)) {
                    return $oResponse;
                }
            }

            $aRoute   = self::matchRoute(self::$aCachedRoutes, $oRequest);
            if ($aRoute) {
                return self::endpoint($aRoute, $oRequest);
            }

            try {
                $oRest   = self::getRestClass($oRequest);

                Log::d('Route.query.rest', [
                    'class'         => get_class($oRest)
                ]);

                if ($oRequest->isOptions()) {
                    $oRest->options();
                    return $oRest->Response;
                }

                $sMethod = strtolower($oRequest->OriginalRequest->getMethod());
                if (method_exists($oRest, $sMethod)) {

                    /** @var ORM\Tables|ORM\Table $oResults */
                    $aRoute = self::matchRoute(self::$aCachedQueryRoutes, $oRequest);
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
                            $oResults = $sClass::$sQueryMethod($aRoute['params']);
                            if ($oResults) {
                                $oRest->setData($oResults);
                            }
                            $oRest->$sMethod();
                        } else {
                            $oRest->Response->setStatus(HTTP\SERVICE_UNAVAILABLE);
                            $oRest->Response->setFormat(Response::FORMAT_EMPTY);
                        }
                    } else {
                        $oResults = self::getQueryFromPath($oRequest);
                        //$oRest->setRequest($oRequest); // TODO: Not Ideal to Re-Set the request here, but ensures we have the latest parsed path values

                        Log::d('Route.query.dynamic', [
                            'path'          => $oRequest->Path,
                            'method'        => $sMethod,
                            'headers'       => $oRequest->OriginalRequest->getHeaders(),
                            'attributes'    => $oRequest->OriginalRequest->getAttributes(),
                            'query'         => $oRequest->GET
                        ]);

                        if ($oResults) {
                            $oRest->setData($oResults);
                        }

                        if (!$oResults && $oRequest->isPut()) { // Tried to PUT with an ID and no record was found
                            $oRest->Response->statusNotFound();
                        } else {
                            $oRest->$sMethod();

                            if ($oResults instanceof ORM\Tables) {
                                // Add the count to the dynamic query output
                                if (self::$oPathQuery instanceof SQLBuilder) {
                                    self::$oPathQuery->setType(SQLBuilder::TYPE_COUNT);

                                    if ($oResult = ORM\Db::getInstance()->namedQuery('getCountQueryFromPath', self::$oPathQuery)) {
                                        if ($oResult->num_rows > 0) {
                                            $oRest->Response->add('counts.' . $oResults->getTable()->getTitle(), (int) $oResult->fetch_object()->row_count);
                                        }
                                    }
                                }
                            }
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
         * @return Rest
         */
        private static function getRestClass(Request $oRequest) {
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
                    $sRestClass = self::getNamespacedAPIClassName($oRequest->Path[0], $sTopClass);
                    if (class_exists($sRestClass)) {
                        return new $sRestClass($oRequest);
                    }
                }
            }

            return new Rest($oRequest);
        }

        protected static function getNamespacedAPIClassName($sAPIClass, $sTopClass) {
            return implode('\\', ['Enobrev', 'API', $sAPIClass, $sTopClass]);
        }

        protected static function getNamespacedTableClassName($sTableClass) {
            return implode('\\', ['Enobrev', 'Table', $sTableClass]);
        }

        /**
         * @param Request $oRequest
         * @return ORM\Table|ORM\Tables
         * @throws ORM\DbException
         * @throws \Exception
         */
        private static function getQueryFromPath(Request &$oRequest) {
            $aPath      = $oRequest->Path;
            $sVersion   = array_shift($aPath); // not using version, but still need to shift
            $aChunks    = $aLastChunk = array_chunk($aPath, 2);

            if (count($aChunks) > 0) {
                $aLastChunk = array_pop($aChunks);

                $sClassName = DataMap::getClassName($aLastChunk[0]);
                if (!$sClassName) {
                    throw new Exception\InvalidTable("Never Heard of " . $aLastChunk[0]);
                }

                $sClass = self::getNamespacedTableClassName($sClassName);

                /** @var ORM\Table $oTable */
                $oTable = new $sClass;
                if ($oTable instanceof ORM\Table === false) {
                    return false;  // TODO: Throw Error
                }

                Log::d('Route.getQueryFromPath.Querying', [
                    'table_class'   => get_class($oTable),
                    'table'         => $oTable->getTitle()
                ]);

                if ($oRequest->isPost() && !isset($aLastChunk[1])) {
                    Log::d('Route.getQueryFromPath.Post.NoId');

                    // Prefill empty POST object with url params
                    while (count($aChunks) > 0) {
                        $aPart = array_shift($aChunks);
                        $sClassName = DataMap::getClassName($aPart[0]);
                        $sClass = self::getNamespacedTableClassName($sClassName);

                        /** @var ORM\Table $oWhereTable */
                        $oWhereTable = new $sClass();

                        if ($oWhereTable instanceof ORM\Table === false) {
                            return false;  // TODO: Throw Error
                        }

                        if (isset($aPart[1])) {
                            $oReference = $oTable->getFieldThatReferencesTable($oWhereTable);
                            if ($oReference instanceof ORM\Field === false) {
                                throw new Exception\InvalidReference("Cannot Associate " . (new \ReflectionClass($oTable))->getShortName() . ' with ' . (new \ReflectionClass($oWhereTable))->getShortName());
                            }

                            $oReference->setValue($aPart[1]);
                            $oRequest->OriginalRequest = $oRequest->OriginalRequest->withAttribute($oReference->sColumn, $aPart[1]);
                        }
                    }

                    return $oTable;
                } else {

                    self::$oPathQuery = SQLBuilder::select($oTable);

                    if (isset($aLastChunk[1])) {
                        self::$oPathQuery->eq_in($oTable->getPrimary()[0], $aLastChunk[1]);
                        $oRequest->OriginalRequest = $oRequest->OriginalRequest->withAttribute($oTable->getPrimary()[0]->sColumn, $aLastChunk[1]);
                    }

                    while (count($aChunks) > 0) {
                        Log::d('Route.getQueryFromPath.Chunks', $aChunks);
                        $aPart = array_shift($aChunks);
                        $sClassName = DataMap::getClassName($aPart[0]);
                        $sClass = self::getNamespacedTableClassName($sClassName);

                        /** @var ORM\Table $oWhereTable */
                        $oWhereTable = new $sClass();

                        if ($oWhereTable instanceof ORM\Table === false) {
                            return false;  // TODO: Throw Error
                        }

                        if (isset($aPart[1])) {
                            Log::d('Route.getQueryFromPath.Chunks.PartWithValue', $aPart);
                            $oReference = $oTable->getFieldThatReferencesTable($oWhereTable);
                            if ($oReference instanceof ORM\Field === false) {
                                throw new Exception\InvalidReference("Cannot Associate " . (new \ReflectionClass($oTable))->getShortName() . ' with ' . (new \ReflectionClass($oWhereTable))->getShortName());
                            }

                            self::$oPathQuery->eq_in($oReference, $aPart[1]);
                            $oRequest->OriginalRequest = $oRequest->OriginalRequest->withAttribute($oReference->sColumn, $aPart[1]);

                            Log::d('Route.getQueryFromPath.Chunks.AddingAttribute', [
                                'field' => $oReference->sColumn,
                                'value' => $aPart[1]
                            ]);
                        }
                    }

                    if (isset($aLastChunk[1])) {
                        Log::d('Route.getQueryFromPath.Querying.HasID');

                        // TABLE
                        if ($oResults = ORM\Db::getInstance()->namedQuery('getQueryFromPath', self::$oPathQuery)) {
                            if ($oResults->num_rows == 1) {
                                Log::d('Route.getQueryFromPath.Querying.HasID.FoundOne');

                                $oTable->setFromObject($oResults->fetch_object());

                                return $oTable;
                            } else if ($oResults->num_rows > 1) {
                                Log::d('Route.getQueryFromPath.Querying.HasID.FoundMultiple');

                                $oOutput = $oTable::getTables();
                                while ($oResult = $oResults->fetch_object()) {
                                    $oOutput->append($oTable::createFromObject($oResult));
                                }

                                return $oOutput;
                            } else if ($oRequest->isPost()) {
                                Log::d('Route.getQueryFromPath.Querying.HasID.FoundNone.Post');

                                $aPrimary = $oTable->getPrimaryFieldNames();
                                if (count($aPrimary) == 1) {
                                    $sPrimary = array_shift($aPrimary);
                                    $oTable->$sPrimary->setValue($aLastChunk[1]);
                                }

                                return $oTable;
                            }
                        }
                    } else {
                        Log::d('Route.getQueryFromPath.Querying.NoID');

                        $iPer   = isset($oRequest->GET['per'])  ? $oRequest->GET['per']  : 1000;
                        $iPage  = isset($oRequest->GET['page']) ? $oRequest->GET['page'] : 1;
                        $iStart = $iPer * ($iPage - 1);

                        self::$oPathQuery->limit($iStart, $iPer);

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
                                        Log::d('Route.getQueryFromPath.Querying.Search', ['field' => $sSearchField, 'value' => $sSearchValue, 'operator' => ':']);

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
                                        Log::d('Route.getQueryFromPath.Querying.Search', ['field' => $sSearchField, 'value' => $sSearchValue, 'operator' => '>']);

                                        if ($oSearchField instanceof ORM\Field\Number) {
                                            $aConditions[] = SQL::gt($oSearchField, $sSearchValue);
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
                                self::$oPathQuery->also(...$aConditions);
                            } else {
                                self::$oPathQuery->either(...$aConditions);
                            }
                        }

                        if (isset($oRequest->GET['sort']) && strlen(trim($oRequest->GET['sort']))) {
                            $sGetSort = trim($oRequest->GET['sort']);
                            $sGetSort = preg_replace('/\,s+/', ',', $sGetSort);
                            $aSort    = explode(',', $sGetSort);

                            foreach($aSort as $sSort) {
                                if (strpos($sSort, '.')) {
                                    $aSort = explode('.', $sSort);
                                    if (count($aSort) == 2) {
                                        $sSortTable = DataMap::getClassName($aSort[0]);
                                        $sSortField = $aSort[1];

                                        Log::d('Route.getQueryFromPath.Querying.NoID.ForeignSort', ['table' => $sSortTable, 'field' => $sSortField]);

                                        $sSortTableClass = self::getNamespacedTableClassName($sSortTable);

                                        /** @var ORM\Table $oSortTable */
                                        $oSortTable = new $sSortTableClass();
                                        if (!$oSortTable instanceof ORM\Table) {
                                            throw new Exception\InvalidTable($sSortTableClass . " is not a valid Table");
                                        }

                                        $oSortReference = $oTable->getFieldThatReferencesTable($oSortTable);
                                        if ($oSortReference instanceof ORM\Field === false) {
                                            throw new Exception\InvalidReference("Cannot Associate " . (new \ReflectionClass($oTable))->getShortName() . ' with ' . (new \ReflectionClass($oSortReference))->getShortName());
                                        }

                                        $sReferenceField = $oSortReference->referenceField();
                                        self::$oPathQuery->join($oSortReference, $oSortTable->$sReferenceField);

                                        $oSortField = DataMap::getField($oSortTable, $sSortField);
                                        self::$oPathQuery->asc($oSortField);
                                    }
                                } else {
                                    $oSortField = DataMap::getField($oTable, $sSort);
                                    if ($oSortField instanceof ORM\Field) {
                                        self::$oPathQuery->asc($oSortField);
                                    }
                                }
                            }
                        }

                        // TABLES
                        if ($oResults = ORM\Db::getInstance()->namedQuery('getQueryFromPath', self::$oPathQuery)) {
                            if ($oResults->num_rows > 0) {
                                $oOutput = $oTable::getTables();
                                while ($oResult = $oResults->fetch_object()) {
                                    $oOutput->append($oTable::createFromObject($oResult));
                                }

                                return $oOutput;
                            }
                        }
                    }
                }
            }

            return false;   // TODO: Throw Error
        }

        /**
         * @param array   $aRoute
         * @param Request $oRequest
         * @return Response|void
         */
        private static function endpoint(Array $aRoute, Request $oRequest) {
            Log::d('Route.endpoint', [
                'class'         => $aRoute['class'],
                'path'          => $oRequest->Path,
                'headers'       => $oRequest->OriginalRequest->getHeaders(),
                'attributes'    => $oRequest->OriginalRequest->getAttributes()
            ]);

            foreach($aRoute['params'] as $sKey => $sValue) {
                $oRequest->OriginalRequest = $oRequest->OriginalRequest->withAttribute($sKey, $sValue);
            }

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
        private static function matchRoute(Array $aRoutes, Request $oRequest) {
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
         * @todo Cache These for Production
         */
        public static function generateRoutes() {
            foreach (self::VERSIONS as $sVersion) {
                foreach (self::ROUTES as $sRoute => $aRoute) {
                    $aRoute['version'] = $sVersion;

                    $sRoute  = str_replace('{version}', $sVersion, $sRoute);
                    $sRoute  = trim($sRoute, '/');
                    $aPath   = explode('/', str_replace('/?', '', $sRoute));

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

                    $sRoute             = implode('/', $aParsed);
                    $sRoute             = str_replace('*', '([^/]+)', $sRoute);
                    $sRoute             = '~' . $sRoute . '~';

                    $aRoute['segments'] = count($aPath);
                    $aRoute['class']    = str_replace('{version}', $sVersion,  $aRoute['class']);
                    $aRoute['params']   = $aParams;
                    self::$aCachedRoutes[$sRoute] = $aRoute;
                }

                foreach (self::QUERIES as $sRoute => $aRoute) {
                    $sRoute  = str_replace('{version}', $sVersion, $sRoute);
                    $sRoute  = trim($sRoute, '/');
                    $aPath   = explode('/', $sRoute);

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

                    $sRoute             = implode('/', $aParsed);
                    $sRoute             = str_replace('*', '([^/]+)', $sRoute);
                    $sRoute             = '~' . $sRoute . '~';

                    $aRoute['segments'] = count($aPath);
                    $aRoute['params']   = $aParams;
                    self::$aCachedQueryRoutes[$sRoute] = $aRoute;
                }
            }
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
        private static function attemptMultiRequest(Request $oRequest) {
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
                                self::attemptRequest($sEndpoint, $aEach);
                            }
                        } else {
                            self::attemptRequest($sEndpoint, $aPost);
                        }
                    }
                } else {
                    while (count($aQuery) > 0) {
                        self::attemptRequest(array_shift($aQuery));
                    }
                }

                $oResponse = new Response($oRequest);
                $oResponse->add(self::$aData);

                return $oResponse;
            }

            return;
        }

        /**
         * @param string $sEndpoint
         * @param array $aPostParams
         */
        private static function attemptRequest($sEndpoint, array $aPostParams = []) {
            try {
                $sEndpoint   = self::fillEndpointTemplateFromData($sEndpoint);
                $aPostParams = self::fillPostTemplateFromData($aPostParams);
            } catch (NoTemplateValuesException $e) {
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

            $oResponse = self::index($aServer, $aGet, $aPostParams);

            if ($oResponse && $oResponse->status == HTTP\OK) {
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
        private static function fillEndpointTemplateFromData($sEndpoint) {
            $aEndpoint  = explode('/', $sEndpoint);
            foreach($aEndpoint as $sSegment) {
                $mTemplateValue = self::getTemplateValue($sSegment);
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
        private static function fillPostTemplateFromData(array $aPost) {
            if (count($aPost)) {
                foreach($aPost as $sParam => $mValue) {
                    $mTemplateValue = self::getTemplateValue($mValue);
                    if ($mTemplateValue !== self::NO_VALUE) {
                        $aPost[$sParam] = $mTemplateValue;
                    }
                }
            }

            return $aPost;
        }

        const NO_VALUE = 'NO_VALUE';

        private static function getTemplateValue($sTemplate) {
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

    Route::generateRoutes();
