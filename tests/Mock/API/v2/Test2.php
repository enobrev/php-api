<?php
    namespace Enobrev\API\Mock\v2;



    use Enobrev\API;

    class Test2 extends API\Base {
        public function methodA() {
            $this->Response->add('test2.v2.method.a', [4, 3, 2]);
        }

        public function methodB() {
            $this->Response->add('test2.v2.method.b', [4, 3, 2]);
        }
    }