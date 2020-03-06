<?php
    namespace Enobrev\API\FullSpec\Component;

    use Adbar\Dot;
    use cebe\openapi\spec\Reference as OpenApi_Reference;
    use cebe\openapi\spec\RequestBody;
    use cebe\openapi\SpecObjectInterface;
    use Enobrev\API\Exception;
    use Enobrev\API\FullSpec\ComponentInterface;
    use Enobrev\API\OpenApiInterface;

    class Request implements ComponentInterface, OpenApiInterface {
        public const PREFIX = 'requestBodies';

        /** @var string */
        private $sName;

        /** @var string */
        private $sDescription;

        /** @var OpenApiInterface */
        private $mPost;

        /** @var OpenApiInterface */
        private $mJson;

        /** @var string */
        private $sDiscriminator;

        /** @var array */
        private $aMapping;

        public static function create(string $sName) {
            return new self($sName);
        }

        public function __construct($sName) {
            $aName = explode('/', $sName);
            if (count($aName) === 1) {
                array_unshift($aName, self::PREFIX);
            } else if ($aName[0] !== self::PREFIX) {
                array_unshift($aName, self::PREFIX);
            }

            $this->sName = implode('/', $aName);
        }

        public function getName(): string {
            return $this->sName;
        }

        public function getPost(): OpenApiInterface {
            return $this->mPost;
        }

        public function getJson(): OpenApiInterface {
            return $this->mJson;
        }

        public function hasDiscriminator(): bool {
            return $this->sDiscriminator !== null;
        }

        public function getDiscriminator(): string {
            return $this->sDiscriminator;
        }

        public function description(string $sDescription):self {
            $this->sDescription = $sDescription;
            return $this;
        }

        public function post(OpenApiInterface $mPost):self {
            $this->mPost = $mPost;
            return $this;
        }

        public function json(OpenApiInterface $mJson):self {
            $this->mJson = $mJson;
            return $this;
        }

        public function discriminator(string $sDiscriminator):self {
            $this->sDiscriminator = $sDiscriminator;
            return $this;
        }

        public function mapping(array $aMapping):self {
            $this->aMapping = $aMapping;
            return $this;
        }

        /**
         * @return array
         * @throws Exception
         */

        public function getSpecObject(): SpecObjectInterface {
            if (!$this->sDescription) {
                throw new Exception('Full Scope Request Components require a description');
            }

            if (!$this->mPost && !$this->mJson) {
                throw new Exception('Full Scope Request Components needs a JSON or form-data schema');
            }

            $oResponse = new Dot([
                'description' => $this->sDescription,
                'content'     => []
            ]);

            if ($this->mPost) {
                $oResponse->set('content.multipart/form-data.schema', json_decode(json_encode($this->mPost->getSpecObject()->getSerializableData()), true));
                if ($this->sDiscriminator) {
                    $oResponse->set('content.multipart/form-data.schema.discriminator.propertyName', $this->sDiscriminator);
                    if ($this->aMapping) {
                        foreach($this->aMapping as $sFrom => $oTo) {
                            $oResponse->set("content.multipart/form-data.schema.discriminator.mapping.$sFrom", $oTo->getSpecObject()->getSerializableData());
                        }
                    }
                }
            }

            if ($this->mJson) {
                $oResponse->set('content.application/json.schema', json_decode(json_encode($this->mJson->getSpecObject()->getSerializableData()), true));
                if ($this->sDiscriminator) {
                    $oResponse->set('content.application/json.schema.discriminator.propertyName', $this->sDiscriminator);
                    if ($this->aMapping) {
                        foreach($this->aMapping as $sFrom => $oTo) {
                            $oResponse->set("content.application/json.schema.discriminator.mapping.$sFrom", $oTo->getSpecObject()->getSerializableData());
                        }
                    }
                }
            }

            return new RequestBody($oResponse->all());
        }
    }