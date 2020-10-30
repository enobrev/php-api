<?php
    namespace Enobrev\API\FullSpec\Component;

    use Adbar\Dot;
    use cebe\openapi\SpecObjectInterface;
    use cebe\openapi\spec\Response as OpenApi_Response;

    use Enobrev\API\FullSpec;
    use Enobrev\API\FullSpec\ComponentInterface;
    use Enobrev\API\OpenApiInterface;

    class Response implements ComponentInterface, OpenApiInterface {
        public const PREFIX = 'responses';

        private string $sName;

        private ?string $sSummary = null;

        private string $sDescription = '';

        /** @var OpenApiInterface[] */
        private array $aSchemas;

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

        public function getDescription(): string {
            return $this->sDescription;
        }

        public function description(string $sDescription):self {
            $this->sDescription = $sDescription;
            return $this;
        }

        public function summary(string $sSummary):self {
            $this->sSummary = $sSummary;
            return $this;
        }

        public function json(OpenApiInterface $mJson):self {
            $this->aSchemas[] = $mJson;
            return $this;
        }

        /**
         * @return SpecObjectInterface
         */
        public function getSpecObject(): SpecObjectInterface {
            if (!count($this->aSchemas)) {
                // If No schema is given, then simply apply the name and description to the default
                return self::create($this->sName)->description($this->sDescription)->json(Reference::create(FullSpec::SCHEMA_DEFAULT))->getSpecObject();
            }

            $oResponse = new Dot([
                'description' => $this->sDescription,
                'content'     => []
            ]);

            if ($this->sSummary) {
                $oResponse->set('x-summary', $this->sSummary);
            }

            foreach($this->aSchemas as $mSubSchema) {
                $oResponse->set('content.application/json.schema', $mSubSchema->getSpecObject());
            }

            return new OpenApi_Response($oResponse->all());
        }
    }