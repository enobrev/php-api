<?php
    namespace Enobrev\API\Mock\v1;



    use Enobrev\API\Base;

    class Test extends Base {
        public function methodA(): void {
            $this->Response->add('test.method.a', [1, 2, 3]);
        }

        public function methodB(): void {
            $this->Response->add('test.method.b', [1, 2, 3]);
        }

        public function detailedMethod(): void {
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