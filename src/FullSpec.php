<?php
    namespace Enobrev\API;

    use Adbar\Dot;
    use function Enobrev\dbg;
    use Enobrev\ORM\Table;
    use TravelGuide\Config;

    class FullSpecException extends Exception {};
    class InvalidResponseException extends FullSpecException {};

    class FullSpec {
        /** @var Dot */
        private $oData;

        /** @var array */
        private $aSchemas;

        /** @var array */
        private $aResponses;

        /** @var Spec[] */
        private $aPaths;

        public function schemas($sSchema, $aSchema) {
            $this->aSchemas[$sSchema] = $aSchema;
        }

        /**
         * @param string $sResponse
         * @param array $aResponse
         * @throws InvalidResponseException
         */
        public function responses(string $sResponse, array $aResponse) {
            if (!isset($aResponse['description'])) {
                throw new InvalidResponseException('Response is Missing description in Spec');
            }

            if (!isset($aResponse['content'])) {
                throw new InvalidResponseException('Response is Missing content in Spec');
            }

            $this->aResponses[$sResponse] = $aResponse;
        }

        public function defaultSchemaResponse(string $sResponse) {
            $this->responses($sResponse, [
                'description' => "A successful response object with the $sResponse data and the standard metadata",
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'allOf' => [
                                ['$ref' => "#/components/schemas/_default"],
                                ['$ref' => "#/components/schemas/$sResponse"],
                            ]
                        ]
                    ]
                ]
            ]);
        }

        public function paths(Spec $oSpec) {
            $this->aPaths["{$oSpec->HttpMethod}.{$oSpec->Path}"] = $oSpec;
        }

        public function getPath(string $sHttpMethod, string $sPath): ?Spec {
            return $this->aPaths["{$sHttpMethod}.{$sPath}"] ?? null;
        }

        public function generateOpenAPI(array $aScopes = []) {
            $sUrl = Config::get('uri/api') . 'v1/';

            $aSecurityFlows = [
                'password' => [
                    'tokenUrl'    => $sUrl . 'auth/client',
                    'refreshUrl'  => $sUrl . 'auth/client',
                    'x-grantType' => 'password',
                    'scopes'      => [
                        'www' => 'General access for the Web Client',
                        'ios' => 'General access for the IOS Client',
                        'cms' => 'General access for the CMS Client'
                    ]
                ],
                'clientCredentials' => [
                    'tokenUrl'    => $sUrl . 'auth/client',
                    'refreshUrl'  => $sUrl . 'auth/client',
                    'x-grantType' => 'client_credentials',
                    'scopes'      => [
                        's2s' => 'General access for backend clients'
                    ]
                ],
                'facebook' => [
                    'tokenUrl'    => $sUrl . 'auth/client',
                    'refreshUrl'  => $sUrl . 'auth/client',
                    'x-grantType' => 'facebook',
                    'scopes'      => [
                        'www' => 'General access for the Web Client',
                        'ios' => 'General access for the IOS Client',
                        'cms' => 'General access for the CMS Client'
                    ]
                ]
            ];

            $aFlows = [];
            if (count($aScopes)) {
                foreach($aSecurityFlows as $sFlow => $aSecurityFlow) {
                    if (count(array_intersect($aScopes, array_keys($aSecurityFlow['scopes'])))) {
                        $aFlows[$sFlow] = $aSecurityFlow;
                        $aFlows[$sFlow]['scopes'] = array_intersect_key($aFlows[$sFlow]['scopes'], array_flip($aScopes));
                    }
                }
            } else {
                $aFlows = $aSecurityFlows;
            }

            $oData = new Dot([
                'openapi' => '3.0.1',
                'info'    => [
                    'title'         => 'Welcome API V1',
                    'description'   => "This is the documentation for Version 1 of the Welcome API.\n\nThis documentation is generated on the fly and so should be completely up to date\n\nThe raw data for this documentation is here: " . Config::get('uri/api') . 'v1/docs',
                    'version'       => '1.0.2',
                    'contact'       => [
                        'name'  => 'Mark Armendariz',
                        'email' => 'src@enobrev.com',
                        'url'   => 'https://github.com/welcotravel/api.welco.me'
                    ],
                    'license'   => [
                        'name'  => '© 2018 Matthew Rosenberg All Rights Reserved'
                    ]
                ],
                'servers' => [
                    [
                        'url'         => $sUrl,
                        'description' => ucwords(Config::get('environment')) . ' API'
                    ]
                ],
                'paths'         => [],
                'components'   => [
                    'schemas' => Spec::DEFAULT_RESPONSE_SCHEMAS,
                    'securitySchemes' => [
                        'OAuth2' => [
                            'type'  => 'oauth2',
                            'flows' => $aFlows
                        ]
                    ]
                ]
            ]);

            $oData->set('components.schemas._any', (object) []);

            $oData->mergeRecursiveDistinct("components.responses", $this->aResponses);
            $oData->mergeRecursiveDistinct("components.schemas", $this->aSchemas);

            /**
             * @var string $sPath
             * @var Spec $oSpec
             */

            foreach($this->aPaths as $oSpec) {
                if (count($aScopes)) {
                    if (count($oSpec->Scopes) && count(array_intersect($aScopes, $oSpec->Scopes))) {
                        $sMethod = strtolower($oSpec->HttpMethod);
                        $oData->set("paths.{$oSpec->Path}.{$sMethod}", $oSpec->generateOpenAPI());
                    }
                } else {
                    $sMethod = strtolower($oSpec->HttpMethod);
                    $oData->set("paths.{$oSpec->Path}.{$sMethod}", $oSpec->generateOpenAPI());
                }
            }

            return $oData;
        }

        public function get($sPath) {
            return $this->oData->get($sPath);
        }

        public function set($sPath, $mValue) {
            return $this->oData->set($sPath, $mValue);
        }

        public function __construct() {
            $this->aPaths     = [];
            $this->aResponses = [];
            $this->aSchemas   = [];
        }
    }
