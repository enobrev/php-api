<?php
    namespace Enobrev\API\Mock\v1;



    use Enobrev;

    class Test2 extends Enobrev\API\Base {
        public function methodA(): void {
            $this->Response->add('test2.method.a', [2, 3, 4]);
        }

        public function methodB(): void {
            $this->Response->add('test2.method.b', [2, 3, 4]);
        }
    }