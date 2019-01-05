<?php
    namespace Enobrev\API;

    use Exception;

    use Enobrev\API\FullSpec\ComponentListInterface;
    use Enobrev\API\FullSpec\Component;
    use Enobrev\API\Spec\AuthenticationErrorResponse;
    use Enobrev\API\Spec\JsonResponse;
    use Enobrev\API\Spec\ProcessErrorResponse;
    use Enobrev\API\Spec\ServerErrorResponse;
    use Enobrev\API\Spec\ValidationErrorResponse;
    use Enobrev\Log;

    use FilesystemIterator;
    use RecursiveIteratorIterator;
    use RecursiveDirectoryIterator;
    use ReflectionClass;
    use ReflectionException;

    use Adbar\Dot;

    class FullSpec {

        const _ANY                          = '_any';
        const _DEFAULT                      = '_default';
        const _CREATED                      = 'Created';
        const _BAD_REQUEST                  = 'BadRequest';
        const _UNAUTHORIZED                 = 'Unauthorized';
        const _FORBIDDEN                    = 'Forbidden';
        const _UNPROCESSABLE_ENTITY         = 'UnprocessableEntiry';
        const _SERVER_ERROR                 = 'ServerError';
        const _MULTI_STATUS                 = 'MultiStatus';

        const SCHEMA_ANY                    = 'schemas/' . self::_ANY;
        const SCHEMA_DEFAULT                = 'schemas/' . self::_DEFAULT;

        const RESPONSE_DEFAULT              = 'responses/' . self::_DEFAULT;
        const RESPONSE_CREATED              = 'responses/' . self::_CREATED;
        const RESPONSE_BAD_REQUEST          = 'responses/' . self::_BAD_REQUEST;
        const RESPONSE_UNAUTHORIZED         = 'responses/' . self::_UNAUTHORIZED;
        const RESPONSE_FORBIDDEN            = 'responses/' . self::_FORBIDDEN;
        const RESPONSE_UNPROCESSABLE_ENTITY = 'responses/' . self::_UNPROCESSABLE_ENTITY;
        const RESPONSE_SERVER_ERROR         = 'responses/' . self::_SERVER_ERROR;
        const RESPONSE_MULTI_STATUS         = 'responses/' . self::_MULTI_STATUS;

        /** @var string */
        private static $sPathToSpec;

        /**
         * @var string
         * @deprecated  Only used in V1
         */
        private static $sAppNamespace;

        /**
         * @var string
         * @deprecated Only used in V1
         */
        private static $sPathToSQLJson;

        /** @var string */
        private static $sPathToAPIClasses;

        /** @var array */
        private static $aVersions;

        /**
         * @var array
         * @deprecated Only used in V1
         */
        private $aSchemas;

        /**
         * @var array
         * @deprecated Only used in V1
         */
        private $aResponses;

        /** @var OpenApiInterface[] */
        private $aComponents;

        /**
         * @var Spec[]
         * @deprecated Only used in V1
         */
        private $aPaths;

        /** @var Spec[] */
        private $aSpecs;

        public function __construct() {
            $this->aComponents      = [];
            $this->aSpecs           = [];
            $this->aPaths           = [];
            $this->aResponses       = [];
            $this->aSchemas         = [];
        }

        /**
         * @param string $sPathToSpec
         * @param string $sPathToSQLJson
         * @param string $sAppNamespace
         * @param string $sPathToAPIClasses
         * @param array $aVersions
         * @throws Exception
         */
        public static function init(string $sPathToSpec, string $sPathToSQLJson, string $sAppNamespace, string $sPathToAPIClasses, array $aVersions): void {
            self::$sPathToSpec          = $sPathToSpec;
            self::$sPathToSQLJson       = $sPathToSQLJson;
            self::$sAppNamespace        = $sAppNamespace;
            self::$sPathToAPIClasses    = $sPathToAPIClasses;
            self::$aVersions            = $aVersions;
        }

        public function toArray():array {
            $aComponents = [];
            foreach($this->aComponents as $sName => $oComponent) {
                $aComponents[$sName] = 'Instance of ' . get_class($oComponent);

            }

            return [
                'versions'   => self::$aVersions,
                'paths'      => [
                    'spec'          => self::$sPathToSpec,
                    'api_classes'   => self::$sPathToAPIClasses
                ],
                'specs'      => $this->aSpecs,
                'components' => $aComponents
            ];
        }

        /**
         * @param $sSchema
         * @param $aSchema
         * @deprecated Only used in v1
         */
        public function schemas($sSchema, $aSchema) {
            return;
            $this->aSchemas[$sSchema] = $aSchema;
        }

        /**
         * @param string $sName
         * @param string $sDescription
         * @param array $aContent
         * @deprecated Only used in v1
         */
        public function responses(string $sName, string $sDescription, array $aContent) {
            return;
            $this->aResponses[$sName] = [
                'description' => $sDescription,
                'content'     => $aContent
            ];
        }

        /**
         * @param string $sResponse
         * @deprecated Only used in v1
         */
        public function defaultSchemaResponse(string $sResponse) {
            return;
            $this->responses($sResponse, "A successful response object with the $sResponse data and the standard metadata", [
                'application/json' => [
                    'schema' => [
                        'allOf' => [
                            ['$ref' => "#/components/schemas/_default"],
                            ['$ref' => "#/components/schemas/$sResponse"],
                        ]
                    ]
                ]
            ]);
        }

        /**
         * @param Spec $oSpec
         * @deprecated Only used in V1
         */
        public function paths(Spec $oSpec) {
            return;
            $sPath       = $oSpec->getPath();
            $sHttpMethod = $oSpec->getHttpMethod();
            if (!isset($this->aPaths[$sPath])) {
                $this->aPaths[$sPath] = [];
            }

            $this->aPaths[$sPath][$sHttpMethod] = $oSpec;
        }

        /**
         * @return FullSpec
         * @throws Exception\Response
         * @throws ReflectionException
         */
        public static function generateAndCache():self {
            $oFullSpec = new self;
            $oFullSpec->generateData();
            file_put_contents(self::$sPathToSpec, serialize($oFullSpec));
            return $oFullSpec;
        }

        /**
         * @return self
         * @throws Exception\Response
         * @throws ReflectionException
         */
        public static function getFromCache(): self {
            if (!file_exists(self::$sPathToSpec)) {
                self::generateAndCache();
            }

            $sFullSpec = file_get_contents(self::$sPathToSpec);

            try {
                return unserialize($sFullSpec);
            } catch (Exception $e) {
                Log::e('FullSpec.getFromCache.Invalid');
                return self::generateAndCache();
            }
        }

        /**
         * ReGenerates the Full Spec every time!!!  This is _SLOW_
         * @return FullSpec
         * @throws Exception\Response
         * @throws ReflectionException
         */
        public static function generateLiveForDevelopment() {
            return self::generateAndCache();
        }

        public function getRoutes() {
            ksort($this->aSpecs, SORT_NATURAL);
            return $this->aSpecs;
        }

        /**
         * @throws Exception\Response
         * @throws ReflectionException
         */
        protected function generateData() {
            $this->specsFromSpecInterfaces();
        }

        /**
         * @param Component\Reference $oReference
         * @return Component\Response
         */
        public function getComponent(Component\Reference $oReference) {
            return $this->aComponents[$oReference->getName()];
        }

        public function followTheYellowBrickRoad(Component\Reference $oReference) {
            $oComponent = $this->getComponent($oReference);
            if ($oComponent instanceof Component\Request) {
                $oSubReference = $oComponent->getJson();
                if ($oSubReference instanceof Component\Reference) {
                    return $this->followTheYellowBrickRoad($oSubReference);
                }
            }

            return $oComponent;
        }

        /**
         * Generates paths and components for openapi spec.  Final spec still requires info and servers stanzas
         * @param array $aScopes
         * @return Dot
         * @throws Exception\Response
         * @throws ReflectionException
         */
        public function getOpenAPI(array $aScopes = []) {
            $oData = new Dot([
                'openapi'   => '3.0.1',
                'info'      => [],
                'servers'   => [],
                'paths'     => [],
                'components' => [
                    'schemas' => self::DEFAULT_RESPONSE_SCHEMAS,
                    'responses' => [
                        self::_DEFAULT => Component\Response::create(self::RESPONSE_DEFAULT)
                            ->summary('OK')
                            ->json(Component\Reference::create(FullSpec::SCHEMA_DEFAULT))
                            ->getOpenAPI(),
                        self::_CREATED => Component\Response::create(self::RESPONSE_CREATED)
                            ->summary('Created')
                            ->json(Component\Reference::create(FullSpec::SCHEMA_DEFAULT))
                            ->description('New record was created.  If a new key was generated for the record, See Location header')
                            ->getOpenAPI(),
                        self::_BAD_REQUEST => Component\Response::create(self::RESPONSE_BAD_REQUEST)
                            ->summary('Bad Request')
                            ->description(<<<DESCRIPTION
### Validation Errors

See `_errors.validation` in the response for details

Validation errors are handled by comparing the data that was sent by the client against the code from which these API documents are generated.

The input parameters in this documentation you're presently reading are documented using `JSONSchema`, and the API utilizes a `JSONSchema`
validation library to ensure the request was formatted according to our specifications.  Because of this, the output of any validation errors 
is a bit different from our standard error / message error responses. 

When dealing with these errors, first look at the `constraint` portion of the error to figure out why what the client sent was not found
acceptable.  If it should be, contact an API developer to adjust the specification to meet the needs of the client in question. 
DESCRIPTION
                            )
                            ->json(
                                ValidationErrorResponse::create()
                                    ->message('Request Validation Error')
                                    ->code(HTTP\BAD_REQUEST)
                            )
                            ->getOpenAPI(),
                        self::_UNAUTHORIZED => Component\Response::create(self::RESPONSE_UNAUTHORIZED)
                            ->summary('Unauthorized')
                            ->description(<<<DESCRIPTION
### Unauthorized Access

See `_errors.authentication` in the response for details

The Access Token in the request was invalid. The client should re-authenticate in order to use this endpoint.
DESCRIPTION
                            )
                            ->json(
                                AuthenticationErrorResponse::create()
                                    ->message('Unauthorized')
                                    ->code(HTTP\UNAUTHORIZED)
                            )
                            ->getOpenAPI(),
                        self::_FORBIDDEN => Component\Response::create(self::RESPONSE_FORBIDDEN)
                            ->summary('Forbidden')
                            ->description(<<<DESCRIPTION
## Forbidden Access

See `_errors.authentication` in the response for details

The authenticated profile does not have access to this endpoint.  The auth token may be for a different scope (like trying 
to use a `cms` token for an `ios` action, or there may be some other reason described in the error output.
DESCRIPTION
                            )
                            ->json(
                                AuthenticationErrorResponse::create()
                                    ->message('Forbidden')
                                    ->code(HTTP\FORBIDDEN)
                            )
                            ->getOpenAPI(),
                        self::_UNPROCESSABLE_ENTITY => Component\Response::create(self::RESPONSE_UNPROCESSABLE_ENTITY)
                            ->summary('Error while Processing Request')
                            ->description(<<<DESCRIPTION
### Process Errors

See `_errors.process` in the response for details

Process errors occur when everything is working as expected, but something went wrong anyway.  This may include validation that runs beyond the
scope of our validation library, which has its own explicit output format (see `400` Request Validation Error documentation for details).
  
When trying to figure out what happened in these cases, keep in mind that process errors were explicitly written into the endpoint source, and are
not part of the standard validation.  If the client developer can't figure out what's going wrong, reviewing the code of the endpoint or discussing it
with the backend team is the best way to resolve the issue.
  
One example of error that falls under this category is when groups of parameters are required 

> `city_id` OR (`latitude` AND `longitude`)  

Our validation library does not enable us to check for this sort of requirement automatically, and so the source of the endpoint would handle that.
Technically the request would have passed validation, and now, as the endpoint tried to process the request, it was not able to for some reason.
DESCRIPTION
                            )
                            ->json(
                                ProcessErrorResponse::create()
                                    ->message('Request was Valid and Server OK, but something else went wrong')
                                    ->code(HTTP\UNPROCESSABLE_ENTITY)
                            )
                            ->getOpenAPI(),
                        self::_SERVER_ERROR => Component\Response::create(self::RESPONSE_SERVER_ERROR)
                            ->summary('Server Error')
                            ->description(<<<DESCRIPTION
### Server Errors

See `_errors.server` in the response

Server errors are meant for unexpected issues on the API server.  Things like uncaught exceptions, database connection issues, and generally
anything that was not foreseen by the backend team.  Basically this is the category of errors that "should not happen" in production.  

In these cases, the client developer should report the error to the backend team.  If the client received a JSON formatted response, 
sending the contents of `_request.logs` to the backend team will help them find the details of the request in order to figure out what went wrong. 

If the contents of the response are empty, send as much information to the backend team as you can gather - including the path, the parameters
and headers and what ever information you have about the response.  A timestamp will also help in this case since there is no log request hash to report.
DESCRIPTION
                            )
                            ->json(
                                ServerErrorResponse::create()
                                    ->message('Something went wrong on the server')
                                    ->code(HTTP\INTERNAL_SERVER_ERROR)
                            )
                            ->getOpenAPI(),
                        self::_MULTI_STATUS => Component\Response::create(self::RESPONSE_MULTI_STATUS)
                            ->json(
                                JsonResponse::create()->allOf([
                                    Component\Reference::create(FullSpec::SCHEMA_DEFAULT),
                                    [
                                        '_request.multiquery' => Component\Reference::create(FullSpec::RESPONSE_DEFAULT)

                                    ]
                                ])
                            )
                            ->description('The overall multi-endpoint query was successful, but some endpoints were not.  See `_request.multiquery` in the response for more information')
                            ->getOpenAPI()
                    ]
                ]
            ]);

            $oData->set('components.schemas._any', (object) []);

            ksort($this->aComponents, SORT_NATURAL); // because why not
            foreach($this->aComponents as $oComponent) {
                $sName = str_replace('/', '.', $oComponent->getName());
                $oData->set("components.$sName", $oComponent->getOpenAPI());
            }

            /**
             * @var string $sPath
             * @var Spec $oSpec
             */
            ksort($this->aSpecs, SORT_NATURAL); // ensures named sub-paths come before {var} subpaths
            foreach($this->aSpecs as $sPath => $aMethods) {
                foreach($aMethods as $sHttpMethod => $sSpecInterface) {
                    /** @var SpecInterface $oSpecInterface */
                    $oSpecInterface = new $sSpecInterface;
                    $oSpec          = $oSpecInterface->spec();

                    if (count($aScopes)) {
                        if (!$oSpec->hasAnyOfTheseScopes($aScopes)) {
                            continue;
                        }
                    }

                    $oData->set("paths.{$oSpec->getPathForDocs()}.{$oSpec->getLowerHttpMethod()}", $oSpec->generateOpenAPI());
                }
            }

            return $oData;
        }

        /**
         * @throws ReflectionException
         */
        private function specsFromSpecInterfaces() {
            foreach (self::$aVersions as $sVersion) {
                $sVersionPath = self::$sPathToAPIClasses . '/' . $sVersion . '/';
                if (file_exists($sVersionPath)) {
                    /** @var \SplFileInfo[] $aFiles */
                    $aFiles  = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator(
                            $sVersionPath,
                            FilesystemIterator::SKIP_DOTS
                        )
                    );

                    $aSortable = [];
                    foreach($aFiles as $oFile) {
                        $aSortable[] = $oFile;
                    }

                    // Sort files by filename for consistent sorting on differing platforms
                    usort($aSortable, function($oFileA, $oFileB) {
                        /**
                         * @var \SplFileInfo $oFileA
                         * @var \SplFileInfo $oFileB
                         */
                        return strnatcmp($oFileA->getRealPath(), $oFileB->getRealPath());
                    });

                    foreach($aSortable as $oFile) {
                        $sContents = file_get_contents($oFile->getPathname());
                        if (preg_match('/namespace\s([^;]+)/', (string) $sContents, $aMatchesNamespace)) {
                            if (preg_match_all('/class\s+([^\s]+)/', (string) $sContents, $aMatchesClass)) {
                                foreach($aMatchesClass[1] as $sClass) {
                                    if (strpos($sClass, 'Exception')) {
                                        continue;
                                    }

                                    $sNamespace = $aMatchesNamespace[1];
                                    $sFullClass = implode('\\', [$sNamespace, $sClass]);

                                    $oReflectionClass = new ReflectionClass($sFullClass);

                                    if ($oReflectionClass->implementsInterface(SpecInterface::class)) {
                                        /** @var SpecInterface $oClass */
                                        $oClass      = new $sFullClass();
                                        $oSpec       = $oClass->spec();
                                        $sPath       = $oSpec->getPath();
                                        $sHttpMethod = $oSpec->getHttpMethod();

                                        if (!isset($this->aSpecs[$sPath])) {
                                            $this->aSpecs[$sPath] = [];
                                        }

                                        $this->aSpecs[$sPath][$sHttpMethod] = $sFullClass;
                                    } else if ($oReflectionClass->implementsInterface(ComponentListInterface::class)) {
                                        /** @var ComponentListInterface $oClass */
                                        $oClass      = new $sFullClass();
                                        $aComponents = $oClass->components();
                                        foreach($aComponents as $sComponent => $oComponent) {
                                            $this->aComponents[$oComponent->getName()] = $oComponent;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        const DEFAULT_RESPONSE_SCHEMAS = [
            "_default" => [
                "type" => "object",
                "properties" => [
                    "_server" => [
                        '$ref' => "#/components/schemas/_server"
                    ],
                    "_request" => [
                        '$ref' => "#/components/schemas/_request"
                    ]
                ]
            ],
            "_server" => [
                "type" => "object",
                "additionalProperties"=> false,
                "properties"=> [
                    "timezone"      => ["type" => "string", "example" => 'CDT'],
                    "timezone_gmt"  => ["type" => "string", "example" => '-05:00'],
                    "date"          => ["type" => "string", "example" => '2018-08-31 16:57:21'],
                    "date_w3c"      => ["type" => "string", "example" => '2018-08-31T16:57:21-05:00']
                ],
                "description" => <<<DESCRIPTION
The `_server` object lists the current time on our backend.  This is useful when trying to use `sync` functionality, 
as well as for converting timestamps returned by the server to the client's local time.  The times given are
retrieved from the database which should ensure that they are consistent, regardless of clock-skew between
multiple servers.
DESCRIPTION
            ],
            "_request" => [
                "type" => "object",
                "additionalProperties"=> false,
                "properties" => [
                    "method"        => [
                        "type" => "string",
                        "enum" => ["GET", "POST", "DELETE"]
                    ],
                    "path"          => ["type" => "string"],
                    "params"         => [
                        "type" => "object",
                        "properties" => [
                            "path" => [
                                "type" => "object",
                                "description" => "Parameters that were found in the URI Path"
                            ],
                            "query" => [
                                "type" => "object",
                                "description" => "Parameters that were found in the URI Search"
                            ],
                            "post" => [
                                "type" => "object",
                                "description" => "Parameters that were found in the Request Body"
                            ]
                        ]
                    ],
                    "headers"   => [
                        "type" => "string",
                        "description" => "JSON Encoded String of request headers"
                    ],
                    "logs"      => [
                        "type" => "object",
                        "properties" => [
                            "thread" => [
                                "type" => "string",
                                "description" => "Alphanumeric hash for looking up entire request thread in logs"
                            ],
                            "request" => [
                                "type" => "string",
                                "description" => "Alphanumeric hash for looking up specific API request in logs"
                            ]
                        ]
                    ],
                    "status" => ["type" => "integer", "default" => 200, "description" => "This will not always show up, but is most useful when trying to find out what happened in `multiquery` requests"],
                    "multiquery" => [
                        "type" => "object",
                        "additionalProperties" => false,
                        "properties" => [
                            "_request" => ['$ref' => '#/components/schemas/_request'],
                            "_server"  => ['$ref' => '#/components/schemas/_server']
                        ],
                        "description" => <<<DESCRIPTION
The `_request.multiquery` object holds a collection of all the `_request` and `_server` objects from every sub-request that was preformed
helps with finding out why you might not have received the data you were expecting. 

This will only show up in cases of multiquery requests.
DESCRIPTION
                    ]
                ],
                "description" => <<<DESCRIPTION
The `_request` object can help the client developer find out if maybe what was sent was misinterpreted by the server for some reason.
DESCRIPTION
            ]
        ];
    }
