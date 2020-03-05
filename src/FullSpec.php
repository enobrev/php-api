<?php
    namespace Enobrev\API;

    use Exception;
    use FilesystemIterator;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;
    use ReflectionClass;
    use ReflectionException;
    use SplFileInfo;

    use Adbar\Dot;
    use cebe\openapi\spec\Components;
    use cebe\openapi\spec\OpenApi;
    use cebe\openapi\spec\PathItem;
    use cebe\openapi\spec\Paths;
    use cebe\openapi\spec\Reference;
    use cebe\openapi\spec\Schema;

    use Enobrev\API\FullSpec\ComponentListInterface;
    use Enobrev\API\FullSpec\Component;
    use Enobrev\API\Spec\AuthenticationErrorResponse;
    use Enobrev\API\Spec\JsonResponse;
    use Enobrev\API\Spec\ProcessErrorResponse;
    use Enobrev\API\Spec\ServerErrorResponse;
    use Enobrev\API\Spec\ValidationErrorResponse;
    use Enobrev\Log;

    class FullSpec {
        public const _ANY                          = '_any';
        public const _DEFAULT                      = '_default';
        public const _CREATED                      = 'Created';
        public const _BAD_REQUEST                  = 'BadRequest';
        public const _UNAUTHORIZED                 = 'Unauthorized';
        public const _FORBIDDEN                    = 'Forbidden';
        public const _UNPROCESSABLE_ENTITY         = 'UnprocessableEntiry';
        public const _SERVER_ERROR                 = 'ServerError';
        public const _MULTI_STATUS                 = 'MultiStatus';

        public const SCHEMA_ANY                    = 'schemas/' . self::_ANY;
        public const SCHEMA_DEFAULT                = 'schemas/' . self::_DEFAULT;

        public const RESPONSE_DEFAULT              = 'responses/' . self::_DEFAULT;
        public const RESPONSE_CREATED              = 'responses/' . self::_CREATED;
        public const RESPONSE_BAD_REQUEST          = 'responses/' . self::_BAD_REQUEST;
        public const RESPONSE_UNAUTHORIZED         = 'responses/' . self::_UNAUTHORIZED;
        public const RESPONSE_FORBIDDEN            = 'responses/' . self::_FORBIDDEN;
        public const RESPONSE_UNPROCESSABLE_ENTITY = 'responses/' . self::_UNPROCESSABLE_ENTITY;
        public const RESPONSE_SERVER_ERROR         = 'responses/' . self::_SERVER_ERROR;
        public const RESPONSE_MULTI_STATUS         = 'responses/' . self::_MULTI_STATUS;

        /** @var string */
        private static $sPathToSpec;

        /** @var string */
        private static $sPathToAPIClasses;

        /** @var array */
        private static $aVersions;

        /** @var OpenApiInterface[] */
        private $aComponents;

        /** @var Spec[] */
        private $aSpecs;

        /** @var self */
        private static $oInstance;

        private final function __clone() {}
        private final function __wakeup() {}
        private function __construct() {
            $this->aComponents      = [];
            $this->aSpecs           = [];
        }

        /**
         * @param string $sPathToSpec
         * @param string $sPathToAPIClasses
         * @param array $aVersions
         */
        public static function init(string $sPathToSpec, string $sPathToAPIClasses, array $aVersions): void {
            self::$sPathToSpec          = $sPathToSpec;
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
         * @return FullSpec
         * @throws ReflectionException
         */
        private static function generateAndCache():self {
            if (self::$oInstance instanceof self) {
                return self::$oInstance;
            }

            self::$oInstance = new self;
            self::$oInstance->generateData();

            if (self::$sPathToSpec) {
                file_put_contents(self::$sPathToSpec, serialize(self::$oInstance));
            }

            return self::$oInstance;
        }

        /**
         * @return self
         * @throws ReflectionException
         */
        public static function getInstance(): ?self {
            if (self::$oInstance instanceof self) {
                return self::$oInstance;
            }

            if (!file_exists(self::$sPathToSpec)) {
                return self::generateAndCache();
            }

            $sFullSpec = file_get_contents(self::$sPathToSpec);

            try {
                self::$oInstance = unserialize($sFullSpec);
                return self::$oInstance;
            } catch (Exception $e) {
                Log::ex('FullSpec.getFromCache.Invalid', $e);
                return self::generateAndCache();
            }
        }

        /**
         * ReGenerates the Full Spec every time!!!  This is _SLOW_
         * @return FullSpec
         * @throws ReflectionException
         */
        public static function generateLiveForDevelopment(): FullSpec {
            self::$oInstance = null;
            return self::generateAndCache();
        }

        /**
         * ensures named sub-paths come before {var} subpaths
         * @return array
         */
        protected function sortPaths(): void {
            foreach($this->aSpecs as $sVersion => &$aSpec) {
                ksort($aSpec, SORT_NATURAL);
            }
            unset($aSpec);
        }

        /**
         * Flatten the specs array for routing
         * @return array
         */
        public function getRoutes(): array {
            $this->sortPaths();

            $aRoutes = [];
            foreach($this->aSpecs as $sVersion => $aPaths) {
                foreach($aPaths as $sPath => $aMethods) {
                    $aRoutes[$sPath] = $aMethods;
                }
            }
            return $aRoutes;
        }

        /**
         * @throws ReflectionException
         */
        protected function generateData(): void {
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
         * @param string|null $sVersion
         * @param array|null  $aScopes
         * @param array       $aOnlyPaths
         *
         * @return OpenApi
         * @throws \cebe\openapi\exceptions\TypeErrorException
         */
        public function getOpenApi(?string $sVersion = null, ?array $aScopes = [], array $aOnlyPaths = []): OpenApi {
            return new OpenApi([
                'openapi'       => '3.0.3',
                'paths'         => $this->getPaths($sVersion, $aScopes, $aOnlyPaths),
                'components'    => $this->getComponents()
            ]);
        }

        /**
         * Generates paths for openapi spec.
         *
         * @param string|null $sVersion
         * @param array|null  $aScopes
         * @param array       $aOnlyPaths
         *
         * @return Paths
         * @throws \cebe\openapi\exceptions\TypeErrorException
         */
        private function getPaths(?string $sVersion = null, ?array $aScopes = [], array $aOnlyPaths = []): Paths {
            $oData = new Dot();

            $oPaths = new Paths([]);
            $this->sortPaths();
            foreach($this->aSpecs as $sSpecVersion => $aPaths) {
                if ($sVersion && $sVersion !== $sSpecVersion) {
                    continue;
                }

                foreach($aPaths as $sPath => $aMethods) {
                    if (count($aOnlyPaths) && !in_array($sPath, $aOnlyPaths)) {
                        continue;
                    }

                    foreach($aMethods as $sHttpMethod => $sSpecInterface) {
                        /** @var SpecInterface $oSpecInterface */
                        $oSpecInterface = new $sSpecInterface;
                        $oSpec          = $oSpecInterface->spec();

                        if (count($aScopes) && !$oSpec->hasAnyOfTheseScopes($aScopes)) {
                            continue;
                        }

                        // It's easiset to simply merge everything here and then sort through the remains
                        $oData->set("{$oSpec->getPathForDocs()}.{$oSpec->getLowerHttpMethod()}", $oSpec->generateOperation());
                    }
                }
            }

            foreach($oData->all() as $sPath => $aPath) {
                $oPathItem = new PathItem([]);
                foreach($aPath as $sMethod => $oOperation) {
                    $oPathItem->$sMethod = $oOperation;
                }
                $oPaths->addPath($sPath, $oPathItem);
            }

            return $oPaths;
        }

        /**
         * Generates components for openapi spec.
         *
         * @return Components
         * @throws \cebe\openapi\exceptions\TypeErrorException
         */
        private function getComponents(): Components {
            $oData = new Dot([
                'responses' => self::getDefaultResponses()
            ]);

            self::setDefaultSchemas($oData);

            ksort($this->aComponents, SORT_NATURAL); // because why not

            foreach($this->aComponents as $oComponent) {
                [$sCategory, $sName] = explode('/', $oComponent->getName());
                $oData->set("$sCategory.$sName", $oComponent->getSpecObject());
            }

            /*
            foreach($oData->all() as $sCategory => $aComponents) {
                foreach($aComponents as $sComponent => $oComponent) {
                    dbg($sCategory . ' - ' . $sComponent . ' - ' . get_class($oComponent));
                }
            }
            */

            return new Components($oData->all());
        }

        /**
         * @param Dot $oData
         *
         * @throws \cebe\openapi\exceptions\TypeErrorException
         */
        private function setDefaultSchemas(Dot &$oData): void {
            $oData->set('schemas._default', new Schema([
                'type'       => 'object',
                'properties' => [
                    '_server'  => new Reference(['$ref' => '#/components/schemas/_server']),
                    '_request' => new Reference(['$ref' => '#/components/schemas/_request'])
                ],
                'description' => 'This schema gets merged with every response as the standard baseline response'
            ]));

            $oData->set('schemas._server', new Schema([
                'type'        => 'object',
                'additionalProperties' => false,
                'properties'  => [
                    'timezone'      => new Schema(['type' => 'string', 'example' => 'CDT']),
                    'timezone_gmt'  => new Schema(['type' => 'string', 'example' => '-05:00']),
                    'date'          => new Schema(['type' => 'string', 'example' => '2018-08-31 16:57:21']),
                    'date_w3c'      => new Schema(['type' => 'string', 'example' => '2018-08-31T16:57:21-05:00'])
                ],
                'description' => <<<DESCRIPTION
The `_server` object lists the current time on our backend.  This is useful when trying to use `sync` functionality, 
as well as for converting timestamps returned by the server to the client's local time.  The times given are
retrieved from the database which should ensure that they are consistent, regardless of clock-skew between
multiple servers.
DESCRIPTION
            ]));

            $oData->set('schemas._request', new Schema([
                'type'       => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'method'      => new Schema(['type' => 'string', 'enum' => ['GET', 'POST', 'DELETE']]),
                    'path'        => new Schema(['type' => 'string']),
                    'params'      => new Schema([
                        'type' => 'object',
                        'properties' => [
                            'path'      => new Schema(['type' => 'object', 'description' => 'Parameters that were found in the URI Path']),
                            'query'     => new Schema(['type' => 'object', 'description' => 'Parameters that were found in the URI Search']),
                            'post'      => new Schema(['type' => 'object', 'description' => 'Parameters that were found in the Request Body']),
                        ],
                    ]),
                    'headers' => new Schema(['type' => 'string', 'description' => 'JSON Encoded String of request headers']),
                    'logs'      => new Schema([
                        'type' => 'object',
                        'properties' => [
                            'thread'      => new Schema(['type' => 'string', 'description' => 'Alphanumeric hash for looking up entire request thread in logs']),
                            'request'     => new Schema(['type' => 'string', 'description' => 'Alphanumeric hash for looking up specific API request in logs'])
                        ],
                    ]),
                    'status' => new Schema(['type' => 'integer', 'default' => 200, 'description' => 'This will not always show up, but is most useful when trying to find out what happened in `multiquery` requests']),
                    'multiquery'      => new Schema([
                        'type' => 'object',
                        'properties' => [
                            '_server'  => new Reference(['$ref' => '#/components/schemas/_server']),
                            '_request' => new Reference(['$ref' => '#/components/schemas/_request'])
                        ],
                         'description'          => <<<DESCRIPTION
The `_request.multiquery` object holds a collection of all the `_request` and `_server` objects from every sub-request that was preformed
helps with finding out why you might not have received the data you were expecting. 

This will only show up in cases of multiquery requests.
DESCRIPTION
                    ]),
                ],
                'description'          => <<<DESCRIPTION
The `_request` object can help the client developer find out if maybe what was sent was misinterpreted by the server for some reason.
DESCRIPTION
            ]));

            $oData->set('schemas._any', new Schema([]));

        }

        /**
         * Generates responses for openapi spec.
         *
         * @return array
         */
        private function getDefaultResponses(): array {
            return [
                self::_DEFAULT => Component\Response::create(self::RESPONSE_DEFAULT)
                    ->summary('OK')
                    ->json(Component\Reference::create(self::SCHEMA_DEFAULT))
                    ->getSpecObject(),
                self::_CREATED => Component\Response::create(self::RESPONSE_CREATED)
                    ->summary('Created')
                    ->json(Component\Reference::create(self::SCHEMA_DEFAULT))
                    ->description('New record was created.  If a new key was generated for the record, See Location header')
                    ->getSpecObject(),
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
                    ->getSpecObject(),
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
                    ->getSpecObject(),
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
                    ->getSpecObject(),
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
                    ->getSpecObject(),
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
                    ->getSpecObject(),
                self::_MULTI_STATUS => Component\Response::create(self::RESPONSE_MULTI_STATUS)
                    ->json(
                        JsonResponse::create()->allOf([
                            Component\Reference::create(self::SCHEMA_DEFAULT),
                            [
                                '_request.multiquery' => Component\Reference::create(self::RESPONSE_DEFAULT)
                            ]
                        ])
                    )
                    ->description('The overall multi-endpoint query was successful, but some endpoints were not.  See `_request.multiquery` in the response for more information')
                    ->getSpecObject()
            ];

        }

        public function addSpec($sVersion, $sPath, $sHttpMethod, $sFullClass): void {
            if (!isset($this->aSpecs[$sVersion])) {
                $this->aSpecs[$sVersion] = [];
            }

            if (!isset($this->aSpecs[$sVersion][$sPath])) {
                $this->aSpecs[$sVersion][$sPath] = [];
            }

            $this->aSpecs[$sVersion][$sPath][$sHttpMethod] = $sFullClass;
        }

        public function addSpecFromInstance(SpecInterface $oSpecInterface): void {
            $oSpec = $oSpecInterface->spec();
            $aPath = explode('/', trim($oSpec->getPath(), '/'));
            $this->addSpec(
                $aPath[0],
                $oSpec->getPath(),
                $oSpec->getHttpMethod(),
                get_class($oSpecInterface)
            );
        }

        /**
         * @param ComponentListInterface $oComponentList
         */
        public function addComponentList(ComponentListInterface $oComponentList): void {
            $aComponents = $oComponentList->components();
            foreach($aComponents as $sComponent => $oComponent) {
                $this->aComponents[$oComponent->getName()] = $oComponent;
            }
        }

        /**
         * This method goes through the given paths and runs the spec() and components() methods in the classes to gather the results
         * @throws ReflectionException
         */
        private function specsFromSpecInterfaces(): void {
            if (!self::$aVersions) {
                return;
            }

            foreach (self::$aVersions as $sVersion) {
                $sVersionPath = self::$sPathToAPIClasses . '/' . $sVersion . '/';
                if (file_exists($sVersionPath)) {
                    /** @var SplFileInfo[] $aFiles */
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
                    usort($aSortable, static function($oFileA, $oFileB) {
                        /**
                         * @var SplFileInfo $oFileA
                         * @var SplFileInfo $oFileB
                         */
                        return strnatcmp($oFileA->getRealPath(), $oFileB->getRealPath());
                    });

                    foreach($aSortable as $oFile) {
                        $sContents = file_get_contents($oFile->getPathname());
                        if (preg_match('/namespace\s([^;]+)/', (string)$sContents, $aMatchesNamespace) &&
                            preg_match_all('/class\s+(\S+)/', (string)$sContents, $aMatchesClass)) {
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

                                        $this->addSpec($sVersion, $sPath, $sHttpMethod, $sFullClass);
                                    } else if ($oReflectionClass->implementsInterface(ComponentListInterface::class)) {
                                        $this->addComponentList(new $sFullClass());
                                    }
                                }
                            }
                    }
                }
            }
        }
    }
