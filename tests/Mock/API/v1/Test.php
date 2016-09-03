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

        public function detailedMethod() {
            $iUserId = $this->Request->OriginalRequest->getAttribute('id');
            if (!$iUserId) {
                $this->Response->statusNotFound();
                return;
            }

            $sCity = $this->Request->OriginalRequest->getAttribute('city');
            if (!$sCity) {
                $this->Response->statusNotFound();
                return;
            }

            $this->Response->add('users.id',   $iUserId);
            $this->Response->add('users.city', $sCity);
        }
    }