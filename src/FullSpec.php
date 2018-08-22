<?php
    namespace Enobrev\API;


    use Adbar\Dot;
    use function Enobrev\dbg;
    use Enobrev\ORM\Table;
    use TravelGuide\Config;

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

        public function responses($sResponse, $aResponse) {
            $this->aResponses[$sResponse] = $aResponse;
        }

        public function paths(Spec $oSpec) {
            $this->aPaths["{$oSpec->HttpMethod}.{$oSpec->Path}"] = $oSpec;
        }

        public function getPath(string $sHttpMethod, string $sPath): Spec {
            return $this->aPaths["{$sHttpMethod}.{$sPath}"];
        }

        public function generateOpenAPI(array $aScopes = []) {
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
                        'url' => 'https://api.welco.me/v1',
                        'description' => 'Production API'
                    ],
                    [
                        'url' => 'https://api.dev.welco.me/v1',
                        'description' => 'Development API'
                    ],
                    [
                        'url' => 'https://api.travel.enobrev.net/v1',
                        'description' => 'Enobrev API'
                    ]
                ],
                'paths'         => [],
                'components'   => [
                    'schemas' => Spec::DEFAULT_RESPONSE_SCHEMAS,
                    'securitySchemes' => [
                        'OAuth2' => [
                            'type'  => 'oauth2',
                            'flows' => [
                                'password' => [
                                    'tokenUrl'   => Config::get('uri/api') . 'v1/auth/client',
                                    'refreshUrl' => Config::get('uri/api') . 'v1/auth/client',
                                    'scopes'     => [
                                        'www' => 'General access for the Web Client',
                                        'ios' => 'General access for the IOS Client',
                                        'cms' => 'General access for the CMS Client'
                                    ]
                                ],
                                'clientCredentials' => [
                                    'tokenUrl'   => Config::get('uri/api') . 'v1/auth/client',
                                    'refreshUrl' => Config::get('uri/api') . 'v1/auth/client',
                                    'scopes'     => [
                                        's2s' => 'General access for backend clients'
                                    ]
                                ]
                                /*
                                'facebook' => [
                                    'tokenUrl'   => Config::get('uri/api') . 'v1/auth/client',
                                    'refreshUrl' => Config::get('uri/api') . 'v1/auth/client',
                                    'scopes'     => [
                                        'www' => 'General access for the Web Client',
                                        'ios' => 'General access for the IOS Client',
                                        'cms' => 'General access for the CMS Client'
                                    ]
                                ]
                                */
                            ]
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
