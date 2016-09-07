<?php
    namespace Enobrev\API\Mock\v1;



    use Enobrev;

    class Test2 extends Enobrev\API\Base {
        public function methodA() {
            $this->Response->add('test2.method.a', [2, 3, 4]);
        }

        public function methodB() {
            $this->Response->add('test2.method.b', [2, 3, 4]);
        }
    }