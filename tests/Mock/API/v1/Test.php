<?php
    namespace Enobrev\API\Mock\v1;

    use function Enobrev\dbg;

    use Enobrev\API\Base;

    class Test extends Base {
        public function methodA() {
            $this->Response->add('test.method.a', [1, 2, 3]);
        }

        public function methodB() {
            $this->Response->add('test.method.b', [1, 2, 3]);
        }
    }