<?php
    namespace Enobrev\API;

    use Enobrev\ORM;
    use Enobrev\ORM\Table;
    use Enobrev\Log;

    trait Restful {
        /** @var  Request */
        public $Request;

        /** @var  Response */
        public $Response;

        /** @var ORM\ModifiedDateColumn[]|ORM\ModifiedDateColumn|ORM\Table|ORM\Tables */
        protected $Data;

        /** @var  string */
        protected $sPath;

        /**
         * Set the Data upon Which the Response is built
         * @param ORM\Table|ORM\Tables $oData
         * @throws \Exception
         */
        public function setData($oData) {
            if ($oData instanceof ORM\Table) {
                $this->sPath = $oData->getTitle();
            } else if ($oData instanceof ORM\Tables) {
                $this->sPath = $oData->getTable()->getTitle();
            }

            $this->Data  = $oData;
        }

        /**
         * @return bool
         */
        public function hasData() {
            return $this->Data instanceof ORM\Table
                || $this->Data instanceof ORM\Tables;
        }

        /**
         * @return ORM\ModifiedDateColumn|ORM\ModifiedDateColumn[]|Table|ORM\Tables
         */
        public function getData() {
            return $this->Data;
        }

        /**
         * HTTP HEAD
         */
        public function head() {
            if ($this->Data instanceof ORM\Tables) {
                $this->heads();
                return;
            }

            $this->Response->setHeadersFromTable($this->Data);
            $this->Response->statusNoContent();
            return;
        }

        /**
         * HTTP HEAD for Multiple Records
         */
        protected function heads() {
            $this->Response->setLastModifiedFromTables($this->Data);
            $this->Response->statusNoContent();
            return;
        }

        /**
         * HTTP GET
         */
        public function get() {
            if ($this->Data instanceof ORM\Tables) {
                $this->gets();
                return;
            }

            if ($this->Data instanceof ORM\Table === false) {
                $this->Response->statusNotFound();
                return;
            }

            $this->Response->add($this->sPath, DataMap::getIndexedResponseMap($this->sPath, $this->Data));
            $this->Response->add('counts.' . $this->sPath, 1);
            $this->Response->add('sorts.' .  $this->sPath, [$this->Data->getPrimary()[0]->getValue()]);
            $this->Response->setHeadersFromTable($this->Data);
            return;
        }

        /**
         * HTTP GET for Multiple Records
         */
        protected function gets() {
            $this->Response->add($this->sPath, DataMap::getIndexedResponseMaps($this->sPath, $this->Data));
            $this->Response->add('sorts.' . $this->sPath, $this->getSorts());
            $this->Response->setLastModifiedFromTables($this->Data);
            return;
        }

        /**
         * @return array
         */
        protected function getSorts() {
            $aSorts = [];
            /** @var ORM\Table $oTable */
            foreach($this->Data as $oTable) {
                $aSorts[] = $oTable->getPrimary()[0]->getValue();
            }

            return $aSorts;
        }

        /**
         * HTTP POST
         */
        public function post() {
            if ($this->Data instanceof ORM\Table === false) {
                $this->Response->statusBadRequest();
                return;
            }

            $this->handleFiles();

            $aOverrides = $this->overridePrimaries();

            if (empty((array) $this->Data->oResult)) { // new record
                foreach ($this->Data->getFields() as $oField) { // Set By Router
                    if ($oField->hasValue() && !$oField->isDefault()) {
                        $aOverrides[$oField->sColumn] = $oField->getValue();
                    }
                }
            }

            Log::d('Rest.post', [
                'this'          => get_class($this),
                'data'          => get_class($this->Data),
                'attributes'    => $this->Request->OriginalRequest->getAttributes(),
                'post'          => $this->Request->POST,
                'mapped'        => DataMap::getResponseMap($this->sPath, $this->Data),
                'overrides'     => $aOverrides
            ]);

            /** @var ORM\Table $oTable */
            $oTable = get_class($this->Data);
            $this->Data = $oTable::createAndUpdateFromMap(
                $this->Request->POST,
                DataMap::getResponseMap($this->sPath, $this->Data),
                $aOverrides
            );

            $this->get();
        }

        /**
         * HTTP PUT
         */
        public function put() {
            if ($this->Data instanceof ORM\Table === false) {
                $this->Response->statusNotFound();
                return;
            }

            $this->handleFiles();

            $aOverridePrimaries = [];
            foreach($this->Data->getPrimary() as $oPrimary) {
                $sColumn = $oPrimary->sColumn;
                $aOverridePrimaries[$sColumn] = $this->Data->$sColumn;
            }

            $aOverridePrimaries = $this->overridePrimaries($aOverridePrimaries);

            Log::d('Rest.put', [
                get_class($this->Data),
                $this->Request->PUT,
                DataMap::getResponseMap($this->sPath, $this->Data),
                $aOverridePrimaries
            ]);

            /** @var ORM\Table $oTable */
            $oTable = get_class($this->Data);
            $this->Data = $oTable::createAndUpdateFromMap(
                $this->Request->PUT,
                DataMap::getResponseMap($this->sPath, $this->Data),
                $aOverridePrimaries
            );

            $this->get();
        }

        /**
         * Grabs Attributes from URI and overrides Fields in POST or PUT data
         * @param array $aOverridePrimaries
         * @return array
         */
        protected function overridePrimaries(array $aOverridePrimaries = []) {
            $aAttributes = $this->Request->OriginalRequest->getAttributes();
            foreach($aAttributes as $sField => $sValue) {
                $oField = $this->Data->$sField;
                if ($oField instanceof ORM\Field) {
                    $aOverridePrimaries[$sField] = $sValue;
                }
            }

            return $aOverridePrimaries;
        }

        /**
         * HTTP DELETE
         */
        public function delete() {
            if ($this->Data instanceof ORM\Table === false) {
                $this->Response->statusNotFound();
                return;
            }

            $this->Data->delete();
            $this->Response->statusNoContent();
            return;
        }

        protected function handleFiles() {

        }
    }
