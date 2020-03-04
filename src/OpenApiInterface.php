<?php
    namespace Enobrev\API;

    use cebe\openapi\SpecObjectInterface;

    interface OpenApiInterface {
        public function getSpecObject(): SpecObjectInterface;
    }