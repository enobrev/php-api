<?php
    namespace Enobrev\API;

    use Enobrev\API\Exception\InvalidRequest;
    use function Enobrev\dbg;
    use Enobrev\Log;

    use RecursiveIteratorIterator;
    use RecursiveDirectoryIterator;
    use FilesystemIterator;
    use SplFileInfo;

    use JmesPath;
    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\ServerRequestFactory;

    use FastRoute;

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

        /** @var bool */
        private static $bOutputServerErrors = false;

        /**
         * @param string $sPathAPI
         * @param string $sNamespaceAPI
         * @param string $sNamespaceTable
         * @param string $sRestClass
         * @param array  $aVersions
         */
        public static function init(string $sPathAPI, string $sNamespaceAPI, string $sNamespaceTable, $sRestClass = Rest::class, array $aVersions = ['v1']): void {
            self::$sPathAPI         = rtrim($sPathAPI, '/') . '/';
            self::$sNamespaceAPI    = trim($sNamespaceAPI, '\\');
            self::$sNamespaceTable  = trim($sNamespaceTable, '\\');
            self::$aVersions        = $aVersions;
            self::$sRestClass       = $sRestClass;
            self::$bReturnResponses = false;

            /** @var Restful $sRest */
            $sRest = self::$sRestClass;
            $sRest::init(self::$sNamespaceTable);
        }

        /**
         * @param bool $bOutputServerErrors
         */
        public static function outputServerErrors(bool $bOutputServerErrors): void {
            self::$bOutputServerErrors = $bOutputServerErrors;
        }

        /**
         * @param ServerRequest $oServerRequest
         * @return \stdClass|null
         * @throws Exception\NoContentType
         */
        public static function index(ServerRequest $oServerRequest = null) {
            $bReturn        = self::$bReturnResponses; // Set this before _getResponse overrides it
            $oServerRequest = $oServerRequest ?? ServerRequestFactory::fromGlobals();
            $oRequest       = new Request($oServerRequest);
            $oResponse      = self::_getResponse($oRequest);

            if ($bReturn) {
                $oOutput = $oResponse->toObject();
                $sOutput = json_encode($oOutput);
                $iOutput = $sOutput ? strlen($sOutput) : 0;

                Log::d('API.Route.index.return', [
                    '#size'    => $iOutput,
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

        const CACHED_ROUTE_BASE    = 'BASE';
        const QUERY_ROUTE_ENDPOINT = 'ENDPOINT';
        const QUERY_ROUTE_TABLE    = 'TABLE';
        const QUERY_ROUTE_REST     = 'REST';


        /**
         * @param string $sVersion
         * @return bool
         */
        public static function isVersion(string $sVersion): bool {
            return in_array($sVersion, self::$aVersions);
        }

        /**
         * @return string
         */
        public static function defaultVersion(): string {
            return self::$aVersions[0];
        }

        /**
         * @param Request $oRequest
         * @return Response
         */
        public static function _getResponse(Request $oRequest): Response {
            try {
                if ($oRequest->isOptions() && $oRequest->pathIsRoot()) {
                    $oRest = new Rest($oRequest);
                    $oRest->Response->respondWithOptions(...Method\_ALL);
                    return $oRest->Response;
                }
                
                if ($oRequest->pathIsRoot()) {
                    Log::d('API.Route._getResponse.root', [
                        '#request' => [
                            'path_normalized' => '/'
                        ]
                    ]);

                    if (!self::$bReturnResponses) {
                        $oResponse = self::_acceptSyncData($oRequest);

                        if ($oResponse) {
                            Log::d('API.Route.query._getResponse.sync', [
                                'response' => get_class($oResponse)
                            ]);

                            $oMultiResponse = self::_attemptMultiRequest($oRequest, $oResponse);

                            if ($oMultiResponse) {
                                Log::d('API.Route.query._getResponse.sync_and_query.respond', [
                                    'response' => get_class($oMultiResponse)
                                ]);

                                return $oMultiResponse;
                            }
                        }
                    }
                }

                // $oFullSpec = FullSpec::getFromCache();
                $oFullSpec = FullSpec::generateLiveForDevelopment(); // FIXME: Remove this

                if ($oRequest->isOptions()) {
                    $aMethods = self::_matchOptions($oFullSpec, $oRequest);
                    $oRest = new Rest($oRequest);
                    if (is_array($aMethods)) {
                        $oRest->Response->respondWithOptions(...$aMethods);
                    } else {
                        $oRest->Response->statusMethodNotAllowed();
                    }
                    return $oRest->Response;
                }

                $oSpec = self::_matchRouteToSpec($oFullSpec, $oRequest);

                if ($oSpec instanceof Spec) {
                    return self::_endpoint($oSpec, $oRequest);
                }

                /** @var Rest $oRest */
                $oRest   = self::_getRestClass($oRequest);
                $sMethod = strtolower($oRequest->OriginalRequest->getMethod());
                if (method_exists($oRest, $sMethod)) {
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
                } else {
                    $oRest->Response->statusMethodNotAllowed();
                }

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

                if (self::$bOutputServerErrors) {
                    $oResponse->add('_server_error', [
                        'type'    => get_class($e),
                        'code'    => $e->getCode(),
                        'message' => $e->getMessage(),
                        'stack'   => $e->getTrace()
                    ]);
                }

                return $oResponse;
            }
        }

        /**
         * move down the path from right to left until we find the segment that represents a table
         * @param Request $oRequest
         * @return RestfulInterface
         * @throws Exception\Response
         */
        public static function _getRestClass(Request $oRequest): RestfulInterface {
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
                        $oClass = new $sRestClass($oRequest);
                        if ($oClass instanceof RestfulInterface) {
                            return $oClass;
                        }
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
        public static function _getNamespacedAPIClassName(string $sVersionPath, string $sAPIClass): string {
            if (self::$sNamespaceAPI === null) {
                throw new Exception\Response('API Route Not Initialized');
            }

            return implode('\\', [self::$sNamespaceAPI, $sVersionPath, $sAPIClass]);
        }

        /**
         * @param Spec $oSpec
         * @param Request $oRequest
         * @return Response
         * @throws Exception
         */
        public static function _endpoint(Spec $oSpec, Request $oRequest) : Response {
            Log::d('API.Route.endpoint', [
                'spec'          => $oSpec->toJson(),
                'headers'       => json_encode($oRequest->OriginalRequest->getHeaders()),
                'attributes'    => json_encode($oRequest->OriginalRequest->getAttributes())
            ]);

            [$sClass, $sMethod] = explode('::', $oSpec->Method);

            /** @var Base $oClass */
            $oClass  = new $sClass($oRequest);
            if ($oClass instanceof Base) {
                try {
                    $oSpec->validateRequest($oRequest, $oClass->Response);

                    if (method_exists($oClass, $sMethod)) {
                        Log::d('API.Route.endpoint.response');
                        if (method_exists($oClass, 'setDataFromPath')) {
                            $oClass->setDataFromPath();
                        }

                        $oClass->$sMethod();
                    } else {
                        Log::w('API.Route.endpoint.methodNotFound');
                        $oClass->methodNotAllowed();
                    }
                } catch (InvalidRequest $e) {
                    Log::e('API.Route.endpoint.InvalidRequest', ['error' => $e]);
                }

                return $oClass->Response;
            } else {
                Log::w('API.Route.endpoint.ClassNotFound');

                throw new Exception('API Route Endpoint Class Was Not Found');
            }
        }

        /**
         * @param array $aRoutes
         * @param Request $oRequest
         * @return array|null
         */
        public static function _matchRoute(Array $aRoutes, Request $oRequest): ?array {
            $sRoute = implode('/', $oRequest->Path);
            $sRoute = trim($sRoute, '/');

            $aRoute = $aRoutes[$sRoute] ?? null;
            if ($aRoute) {
                Log::d('API.Route._matchRoute', [
                    '#request' => [
                        'path_normalized' => '/' . $aRoute['normalized']
                    ]
                ]);
            }

            return $aRoute;
        }

        /**
         * @param FullSpec $oFullSpec
         * @param Request &$oRequest
         * @return Spec|null
         */
        public static function _matchRouteToSpec(FullSpec $oFullSpec, Request &$oRequest): ?Spec {
            $oDispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) use ($oFullSpec) {
                $aRoutes = $oFullSpec->getRoutes();
                foreach($aRoutes as $sPath => $aMethods) {
                    $r->addRoute($aMethods, $sPath, $sPath);
                }
            });

            $aPath = $oRequest->Path;
            if ($aPath[0] == 'v1') { // FIXME: Include Version in Spec Paths
                array_shift($aPath);
            }

            $sPath = '/' . implode('/', $aPath);
            $aRouteInfo = $oDispatcher->dispatch($oRequest->Method, $sPath);

            switch ($aRouteInfo[0]) {
                case FastRoute\Dispatcher::NOT_FOUND:
                    /*
                    dbg('NOT FOUND');
                    $aRoutes = $oFullSpec->getRoutes();
                    dbg($sPath, $aRoutes);
                    */
                    return null;
                    break;
                case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                    /*
                    dbg($oRequest->Method);
                    dbg('NOT ALLOWED', $aRouteInfo);
                    */
                    return null;
                    break;
                case FastRoute\Dispatcher::FOUND:
                    if ($aRouteInfo[2]) {
                        $oRequest->updatePathParams($aRouteInfo[2]);
                    }

                    /*
                    dbg($aRouteInfo);
                    dbg($oFullSpec->getPath($aRouteInfo[1], $oRequest->Method));
                    */
                    return $oFullSpec->getPath($aRouteInfo[1], $oRequest->Method);
                    break;
            }

            /*
            $aRoute = $aRoutes[$sRoute] ?? null;
            if ($aRoute) {
                Log::d('API.Route._matchRoute', [
                    '#request' => [
                        'path_normalized' => '/' . $aRoute['normalized']
                    ]
                ]);
            }

            return $aRoute;
            */
        }

        /**
         * @param FullSpec $oFullSpec
         * @param Request $oRequest
         * @return string[]|null
         */
        public static function _matchOptions(FullSpec $oFullSpec, Request $oRequest): ?array {
            $oDispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) use ($oFullSpec) {
                $aRoutes = $oFullSpec->getRoutes();
                foreach($aRoutes as $sPath => $aMethods) {
                    try {
                        $r->addRoute('OPTIONS', $sPath, $aMethods);
                    } catch (FastRoute\BadRouteException $e) {
                        // Don't worry about it - not important for options
                    }
                }
            });

            $aPath = $oRequest->Path;
            if ($aPath[0] == 'v1') { // FIXME: Include Version in Spec Paths
                array_shift($aPath);
            }

            $sPath = '/' . implode('/', $aPath);
            $aRouteInfo = $oDispatcher->dispatch($oRequest->Method, $sPath);

            switch ($aRouteInfo[0]) {
                case FastRoute\Dispatcher::NOT_FOUND:
                    return null;
                    /*
                    dbg('NOT FOUND');
                    $aRoutes = $oFullSpec->getRoutes();
                    dbg($sPath, $aRoutes);
                    */
                    break;
                case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                    return null;
                    /*
                    dbg($oRequest->Method);
                    dbg('NOT ALLOWED', $routeInfo);
                    */
                    break;
                case FastRoute\Dispatcher::FOUND:
                    return $aRouteInfo[1];
                    break;
            }

            /*
            $aRoute = $aRoutes[$sRoute] ?? null;
            if ($aRoute) {
                Log::d('API.Route._matchRoute', [
                    '#request' => [
                        'path_normalized' => '/' . $aRoute['normalized']
                    ]
                ]);
            }

            return $aRoute;
            */
        }

        /** @var array  */
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
         * @return Response|null
         */
        public static function _acceptSyncData(Request $oRequest): ?Response {
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

            return null;
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
         * @param array  $aPostParams
         * @throws Exception\NoContentType
         * @throws \Exception
         */
        public static function _attemptRequest(string $sEndpoint, array $aPostParams = []): void {
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
            } catch (Exception\InvalidSegmentVariable $e) {
                Log::e('API.Route._attemptRequest.skipped.invalid.endpoint', [
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

                $aResponseParsed = json_decode((string) json_encode($oResponse->data), true); // FIXME: Inefficient and silly object to array conversion

                if (isset($aResponseParsed['_request'])) {
                    self::$aData['__requests'][] = $aResponseParsed['_request'];
                    unset($aResponseParsed['_request']);
                }

                if (isset($aResponseParsed['_server'])) {
                    unset($aResponseParsed['_server']);
                }

                if (isset($aResponseParsed['counts'])) {
                    if (!isset(self::$aData['counts'])) {
                        self::$aData['counts'] = [];
                    }

                    foreach($aResponseParsed['counts'] as $sPath => $iCount) {
                        if (is_array($iCount)) {
                            self::$aData['counts'][$sPath] = $iCount;
                        } else {
                            if (!isset(self::$aData['counts'][$sPath])) {
                                self::$aData['counts'][$sPath] = 0;
                            }

                            self::$aData['counts'][$sPath] += $iCount;
                        }
                    }

                    unset($aResponseParsed['counts']);
                }

                if ($aResponseParsed) {
                    foreach ($aResponseParsed as $sTable => $aRecords) {
                        if (!isset(self::$aData[$sTable])) {
                            self::$aData[$sTable] = [];
                        }

                        if (is_iterable($aRecords)) {
                            foreach ($aRecords as $sId => $aRecord) {
                                self::$aData[$sTable][$sId] = $aRecord;
                            }
                        }
                    }
                }
            } else if ($oResponse) {
                switch($oResponse->status) {
                    case HTTP\NO_CONTENT:
                        Log::i('API.Route._attemptRequest.Done', [
                            'endpoint' => $sEndpoint,
                            'status'   => $oResponse->status,
                            'headers'  => json_encode($oResponse->headers),
                            'body'     => json_encode($oResponse->data),
                            '--ms'     => $nRequestTimer
                        ]);
                        break;

                    case HTTP\NOT_FOUND:
                        Log::w('API.Route._attemptRequest.Done', [
                            'endpoint' => $sEndpoint,
                            'status'   => $oResponse->status,
                            'headers'  => json_encode($oResponse->headers),
                            'body'     => json_encode($oResponse->data),
                            '--ms'     => $nRequestTimer
                        ]);
                        break;

                    default:
                        Log::setProcessIsError(true);
                        Log::e('API.Route._attemptRequest.Done', [
                            'endpoint' => $sEndpoint,
                            'status'   => $oResponse->status,
                            'headers'  => json_encode($oResponse->headers),
                            'body'     => json_encode($oResponse->data),
                            '--ms'     => $nRequestTimer
                        ]);
                        break;
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
            //dbg($sEndpoint);
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

        /**
         * @param string $sTemplate
         * @return string
         * @throws Exception\InvalidSegmentVariable
         * @throws Exception\NoTemplateValues
         * @throws Exception\InvalidJmesPath
         */
        public static function _getTemplateValue(string $sTemplate) {
            if (strpos($sTemplate, '{') === 0) {
                $aValues = [];
                $sMatch  = trim($sTemplate, "{}");

                if (strpos($sMatch, 'jmes:') === 0) {
                    $sExpression = str_replace('jmes:', '', $sMatch);

                    try {
                        $aValues = JmesPath\Env::search($sExpression, self::$aData);
                    } catch (\RuntimeException $e) {
                        Log::e('Route.getTemplateValue.JMESPath.error', [
                            'template'   => $sTemplate,
                            'expression' => $sExpression,
                            'error'      => $e
                        ]);

                        $aValues = [];
                    }

                    if ($aValues) {
                        if (!is_array($aValues)) {
                            $aValues = [$aValues];
                        } else if (count($aValues) && is_array($aValues[0])) { // cannot work with a multi-array
                            Log::e('Route.getTemplateValue.JMESPath', [
                                'template'   => $sTemplate,
                                'expression' => $sExpression,
                                'values'     => $aValues
                            ]);

                            throw new Exception\InvalidJmesPath('JmesPath Needs to return a flat array, this was a multidimensional array.  Consider the flatten projection operator []');
                        }
                    }

                    Log::d('Route.getTemplateValue.JMESPath', [
                        'template'   => $sTemplate,
                        'expression' => $sExpression,
                        'values'     => $aValues
                    ]);
                } else {
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

                            Log::d('Route.getTemplateValue.TableField', [
                                'template' => $sTemplate,
                                'values'   => $aValues
                            ]);
                        }
                    }
                }

                if (count($aValues)) {
                    $aUniqueValues = array_unique(array_filter($aValues));
                    if (count($aValues) > 0 && count($aUniqueValues) == 0) {
                        throw new Exception\NoTemplateValues();
                    }

                    return implode(',', $aUniqueValues);
                }
            }

            return $sTemplate;
        }
    }