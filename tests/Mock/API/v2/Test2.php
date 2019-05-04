<?php
    namespace Enobrev\API\Mock\v2;



    use Enobrev\API;

    class Test2 extends API\Base {
        public function methodA(): void {
            $this->Response->add('test2.v2.method.a', [4, 3, 2]);
        }

        public function methodB(): void {
            $this->Response->add('test2.v2.method.b', [4, 3, 2]);
        }
    }