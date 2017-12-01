<?php
    namespace Enobrev\API;

    use function Enobrev\dbg;
    use Enobrev\Log;

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
        public static function init(string $sPathAPI, string $sNamespaceAPI, string $sNamespaceTable, $sRestClass = Rest::class, array $aVersions = ['v1']) {
            self::$sPathAPI         = rtrim($sPathAPI, '/') . '/';
            self::$sNamespaceAPI    = trim($sNamespaceAPI, '\\');
            self::$sNamespaceTable  = trim($sNamespaceTable, '\\');
            self::$aVersions        = $aVersions;
            self::$sRestClass       = $sRestClass;
            self::$bReturnResponses = false;

            /** @var Restful $sRest */
            $sRest = self::$sRestClass;
            $sRest::init(self::$sNamespaceTable);

            self::_generateRoutes();
        }

        /**
         * @param ServerRequest $oServerRequest
         * @return \stdClass|null
         */
        public static function index(ServerRequest $oServerRequest = null) {
            $bReturn        = self::$bReturnResponses; // Set this before _getResponse overrides it
            $oServerRequest = $oServerRequest ?? ServerRequestFactory::fromGlobals();
            $oRequest       = new Request($oServerRequest);
            $oResponse      = self::_getResponse($oRequest);

            if ($bReturn) {
                $oOutput = $oResponse->toObject();

                Log::d('API.Route.index.return', [
                    '#size'    => strlen(json_encode($oOutput)),
                    '#status'  => $oOutput->status,
                    '#headers' => json_encode($oOutput->headers),
                    'body'     => json_encode($oOutput->data)
                ]);

                return $oOutput;
            } else {
                Log::d('API.Route.index.respond');
                $oResponse->respond();
            }
        }

        const QUERY_ROUTE_ENDPOINT = 'ENDPOINT';
        const QUERY_ROUTE_TABLE    = 'TABLE';
        const QUERY_ROUTE_REST     = 'REST';

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
         * @param string $sRoute
         * @param string $sClass
         */
        public static function addRestRoute(string $sRoute, string $sClass) {
            self::addQueryRoute(self::QUERY_ROUTE_REST, $sRoute, $sClass, self::QUERY_ROUTE_REST);
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
                $sVersionedRoute = '~^' . $sVersionedRoute . '$~';

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
            if ($oRequest->pathIsRoot() && !$oRequest->isOptions()) {
                Log::d('API.Route._getResponse.root', [
                    '#request' => [
                        'path_normalized' => '/'
                    ]
                ]);

                if (!self::$bReturnResponses) {
                    $oResponse = self::_acceptSyncData($oRequest);
                    Log::d('API.Route.query._getResponse.sync', [
                        'response' => get_class($oResponse)
                    ]);

                    $oResponse = self::_attemptMultiRequest($oRequest, $oResponse);

                    Log::d('API.Route.query._getResponse.sync_and_query', [
                        'response' => get_class($oResponse)
                    ]);

                    if ($oResponse) {
                        Log::d('API.Route.query._getResponse.sync_and_query.respond');
                        return $oResponse;
                    }
                }
            }

            try {
                $aRoute   = self::_matchRoute(self::$aCachedRoutes, $oRequest);
                if ($aRoute) {
                    return self::_endpoint($aRoute, $oRequest);
                }

                /** @var Rest $oRest */
                $oRest   = self::_getRestClass($oRequest);

                Log::d('API.Route.query.rest', [
                    'class' => get_class($oRest)
                ]);

                if ($oRequest->isOptions()) {
                    $oRest->options();
                    return $oRest->Response;
                }

                $sMethod = strtolower($oRequest->OriginalRequest->getMethod());
                if (method_exists($oRest, $sMethod)) {
                    $aRoute = self::_matchQuery(self::$aCachedQueryRoutes, $oRequest);
                    if ($aRoute) {
                        $sClass       = $aRoute['class'];
                        $sQueryMethod = $aRoute['method'] == self::QUERY_ROUTE_REST ? $oRequest->Method : $aRoute['method'];

                        Log::d('API.Route.query.cached', [
                            'class'      => $sClass,
                            'method'     => $sQueryMethod,
                            'path'       => $oRequest->Path,
                            'headers'    => json_encode($oRequest->OriginalRequest->getHeaders()),
                            'attributes' => json_encode($oRequest->OriginalRequest->getAttributes())
                        ]);

                        if (method_exists($sClass, $sQueryMethod)) {
                            // If Class is a Table, then use Rest and setData from that Method, otherwise, just run the Method
                            switch ($aRoute['type']) {
                                case self::QUERY_ROUTE_TABLE:
                                    /** @var \Enobrev\ORM\Tables|\Enobrev\ORM\Table $oResults */
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

                                case self::QUERY_ROUTE_REST:
                                    $oRequest->updateParams($aRoute['params']);

                                    /** @var Rest $oClass */
                                    $oClass = new $sClass($oRequest);
                                    $oClass->setDataFromPath();
                                    $oClass->$sQueryMethod();

                                    return $oClass->Response;
                                    break;
                            }
                        } else {
                            $oRest->Response->setStatus(HTTP\SERVICE_UNAVAILABLE);
                            $oRest->Response->setFormat(Response::FORMAT_EMPTY);
                        }
                    } else if ($oRest->Request->pathIsRoot()) {
                        Log::d('API.Route.root', [
                            '#request' => [
                                'path_normalized' => '/'
                            ],
                            'path'          => $oRequest->Path,
                            'method'        => $sMethod,
                            'headers'       => json_encode($oRequest->OriginalRequest->getHeaders()),
                            'attributes'    => json_encode($oRequest->OriginalRequest->getAttributes()),
                            'query'         => json_encode($oRequest->GET)
                        ]);

                        if (method_exists($oRest, 'index')) {
                            $oRest->index();
                        } else {
                            $oRest->Response->statusNoContent();
                        }
                    } else {
                        $oRest->setDataFromPath();

                        Log::d('API.Route.query.dynamic', [
                            '#request' => [
                                'path_normalized' => '/' . $oRest->getDataPath()
                            ],
                            'path'          => implode('/', $oRequest->Path),
                            'method'        => $sMethod,
                            'headers'       => json_encode($oRequest->OriginalRequest->getHeaders()),
                            'attributes'    => json_encode($oRequest->OriginalRequest->getAttributes()),
                            'query'         => json_encode($oRequest->GET)
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
                Log::setProcessIsError(true);
                Log::c('API.Route._getRequest.Error', [
                    'request' => [
                        'path'      => $oRequest->OriginalRequest->getUri()->getPath(),
                        'headers'   => json_encode($oRequest->OriginalRequest->getHeaders()),
                        'params'    => $oRequest->OriginalRequest->getParsedBody()
                    ],
                    '#error' => [
                        'type'    => get_class($e),
                        'code'    => $e->getCode(),
                        'message' => $e->getMessage(),
                        'stack'   => json_encode($e->getTrace())
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
         * @param array   $aRoute
         * @param Request $oRequest
         * @return Response|null
         */
        public static function _endpoint(Array $aRoute, Request $oRequest) : ?Response {
            Log::d('API.Route.endpoint', [
                'class'         => $aRoute['class'],
                'path'          => $oRequest->Path,
                'headers'       => json_encode($oRequest->OriginalRequest->getHeaders()),
                'attributes'    => json_encode($oRequest->OriginalRequest->getAttributes())
            ]);

            /** @var Base $oClass */
            $oClass  = new $aRoute['class']($oRequest);
            $sMethod = strtolower($oRequest->OriginalRequest->getMethod());

            if ($oClass instanceof Base) {
                if (isset($aRoute['method'])) {
                    $sMethod = $aRoute['method'];
                }

                if (method_exists($oClass, $sMethod)) {
                    Log::d('API.Route.endpoint.response');
                    $oClass->$sMethod();
                } else {
                    Log::w('API.Route.endpoint.methodNotFound');
                    $oClass->methodNotAllowed();
                }

                return $oClass->Response;
            } else {
                // FIXME: return error;
                Log::w('API.Route.endpoint.ClassNotFound');
            }

            return null;
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
                Log::d('API.Route._matchRoute', [
                    '#request' => [
                        'path_normalized' => '/' . $sRoute
                    ]
                ]);

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
                return $aRoute['segments'] == $iSegments || ($aRoute['type'] == self::QUERY_ROUTE_REST && $aRoute['segments'] == $iSegments - 1);
            });

            foreach ($aPossible as $sMatch => $aRoute) {
                $aMatches = [];
                if (preg_match($sMatch, $sRoute, $aMatches)) {
                    if (isset($aRoute['options']) && is_array($aRoute['options']) && count($aRoute['options']) > 0) {
                        if (!in_array(strtoupper($oRequest->Method), $aRoute['options'])) {
                            continue;
                        }
                    }

                    Log::d('API.Route._matchQuery', [
                        '#request' => [
                            'path_normalized' => '/' . $sRoute
                        ]
                    ]);

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
         * @param  Request       $oRequest
         * @param  Response|null $oSyncResponse
         * @return Response|null
         */
        public static function _attemptMultiRequest(Request $oRequest, Response $oSyncResponse = null) {
            if (self::$bReturnResponses) {
                return;
            }

            Log::d('API.Route._attemptMultiRequest');

            self::$bReturnResponses = true;

            if (!isset(self::$aData['__requests'])) {
                self::$aData['__requests'] = [];
            }

            if (isset($oRequest->POST['__query'])) {
                $aQuery = is_array($oRequest->POST['__query']) ? $oRequest->POST['__query'] : json_decode($oRequest->POST['__query']);

                if (array_is_multi($aQuery)) {
                    foreach ($aQuery as $sEndpoint => $aPost) {
                        self::_attemptRequest($sEndpoint, $aPost);
                    }
                } else {
                    while (count($aQuery) > 0) {
                        self::_attemptRequest(array_shift($aQuery));
                    }
                }

                Log::d('API.Route._attemptMultiRequest.done', [
                    'path'       => $oRequest->Path,
                    'headers'    => json_encode($oRequest->OriginalRequest->getHeaders()),
                    'attributes' => json_encode($oRequest->OriginalRequest->getAttributes())
                ]);

                $oResponse = $oSyncResponse ? $oSyncResponse : new Response($oRequest);
                $oResponse->add(self::$aData);

                return $oResponse;
            } else if ($oSyncResponse) {
                return $oSyncResponse;
            }

            self::$bReturnResponses = false;
        }

        /**
         * @param Request $oRequest
         * @return Response
         */
        public static function _acceptSyncData(Request $oRequest) {
            Log::d('API.Route._acceptSyncData');

            if (count($oRequest->POST)) {
                self::$aData['__requests'] = [];
                self::$bReturnResponses = true;

                foreach($oRequest->POST as $sTable => $aRecords) {
                    if (substr($sTable, 0, 1) == '_') {
                        continue;
                    }

                    if (!DataMap::hasClassPath($sTable)) {
                        continue;
                    }

                    foreach($aRecords as $sPrimary => $aRecord) {
                        Log::d('API.Route._acceptSyncData.attempt', [
                            'endpoint'  => "$sTable/$sPrimary",
                            'POST'      => json_encode($aRecord)
                        ]);
                        self::_attemptRequest("$sTable/$sPrimary", $aRecord);
                    }
                }

                self::$bReturnResponses = false;

                Log::d('API.Route._acceptSyncData.done');

                $oResponse = new Response($oRequest);
                $oResponse->add(self::$aData);

                return $oResponse;
            }
        }

        /**
         * Replica of ServerRequestFactory, because we do NOT want ServerRequest::getBody()->getContents() to work, and the factory forces php://input for the request body
         * @param array $aServer
         * @param array $aGet
         * @param array $aPostParams
         * @return ServerRequest
         */
        private static function _serverRequestFactory(array $aServer, array $aGet, array $aPostParams) {
            $aServer  = ServerRequestFactory::normalizeServer($aServer);
            $aHeaders = ServerRequestFactory::marshalHeaders($aServer);

            return new ServerRequest(
                $aServer,
                [],
                ServerRequestFactory::marshalUriFromServer($aServer, $aHeaders),
                ServerRequestFactory::get('REQUEST_METHOD', $aServer, 'GET'),
                'php://memory',
                $aHeaders,
                $_COOKIE,
                $aGet,
                $aPostParams,
                $aServer['SERVER_PROTOCOL'] ?? '1.1' // Wonk because the marshal method is private
            );
        }

        /**
         * @param string $sEndpoint
         * @param array $aPostParams
         */
        public static function _attemptRequest($sEndpoint, array $aPostParams = []) {
            $sTimerName = 'Route._attemptRequest';
            Log::startTimer($sTimerName);

            try {
                $sEndpoint   = self::_fillEndpointTemplateFromData($sEndpoint);
                $aPostParams = self::_fillPostTemplateFromData($aPostParams);
            } catch (Exception\NoTemplateValues $e) {
                Log::e('API.Route._attemptRequest.skipped.missing.keys', [
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

            Log::i('API.Route._attemptRequest', [
                'endpoint'  => $sEndpoint,
                'get'       => json_encode($aGet),
                'post'      => json_encode($aPostParams)
            ]);

            Log::startChildRequest();
            $oResponse = self::index(self::_serverRequestFactory($aServer, $aGet, $aPostParams));
            Log::endChildRequest();

            $nRequestTimer = Log::stopTimer($sTimerName);

            if ($oResponse && $oResponse->status == HTTP\OK) { //  || $oResponse->status == HTTP\NOT_FOUND // Return the 0-count
                Log::i('API.Route._attemptRequest.Done', [
                    'endpoint' => $sEndpoint,
                    'status'   => $oResponse->status,
                    'headers'  => json_encode($oResponse->headers),
                    'body'     => json_encode($oResponse->data),
                    '--ms'     => $nRequestTimer
                ]);

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

                if ($aResponseParsed) {
                    foreach ($aResponseParsed as $sTable => $aRecords) {
                        if (!isset(self::$aData[$sTable])) {
                            self::$aData[$sTable] = [];
                        }

                        foreach ($aRecords as $sId => $aRecord) {
                            self::$aData[$sTable][$sId] = $aRecord;
                        }
                    }
                }
            } else if ($oResponse) {
                if ($oResponse->status == HTTP\NOT_FOUND) {
                    Log::w('API.Route._attemptRequest.Done', [
                        'endpoint' => $sEndpoint,
                        'status'   => $oResponse->status,
                        'headers'  => json_encode($oResponse->headers),
                        'body'     => json_encode($oResponse->data),
                        '--ms'     => $nRequestTimer
                    ]);
                } else {
                    Log::setProcessIsError(true);
                    Log::e('API.Route._attemptRequest.Done', [
                        'endpoint' => $sEndpoint,
                        'status'   => $oResponse->status,
                        'headers'  => json_encode($oResponse->headers),
                        'body'     => json_encode($oResponse->data),
                        '--ms'     => $nRequestTimer
                    ]);
                }
            } else {
                Log::w('API.Route._attemptRequest.Done', [
                    'endpoint' => $sEndpoint,
                    '--ms'     => $nRequestTimer
                ]);
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
            $bMatched = preg_match_all('/{[^}]+}/', $sEndpoint, $aMatches);
            if ($bMatched && count($aMatches) > 0) {
                $aTemplates = $aMatches[0];
                foreach ($aTemplates as $sTemplate) {
                    $mTemplateValue = self::_getTemplateValue($sTemplate);
                    if ($mTemplateValue !== self::NO_VALUE) {
                        $sEndpoint = str_replace($sTemplate, $mTemplateValue, $sEndpoint);
                    }
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
                    if (is_array($mValue)) {
                        continue;
                    }

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
                                if (is_array($aTable[$sField])) {
                                    /*
                                     Handles consolidated arrays of ids.  Example:

                                        "table": {
                                            "{id}": {
                                                "column": [
                                                    "id1"
                                                ],
                                                "column2": [
                                                    "id2",
                                                    "id3",
                                                    "id4"
                                                ]
                                            }
                                        }

                                        - If you then query like so:

                                        "/table/",
                                        "/reference_table/{table.column}",
                                        "/reference_table/{table.column2}"

                                        table.column and table.column2 are arrays of Ids in this instance and need to be handled thusly
                                    */
                                    $aValues = array_merge($aValues, $aTable[$sField]);
                                } else {
                                    $aValues[] = $aTable[$sField];
                                }
                            } else if (is_array(self::$aData[$sTable]) && isset(self::$aData[$sTable][$sField])) {
                                // Single-Record response (like /me)
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