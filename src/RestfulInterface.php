<?php
    namespace Enobrev\API;


    interface RestfulInterface {
        public function setData($oData);
        public function hasData();
        public function getData();
        public function head();
        public function get();
        public function post();
        public function put();
        public function delete();
        public function options();
        public function spec(FullSpec &$oFullSpec);
    }