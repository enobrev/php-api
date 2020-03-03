<?php
    namespace Enobrev\API;

    use cebe\openapi\SpecObjectInterface;

    interface OpenApiInterface {
        public function getOpenAPI(): array;
        public function getSpecObject(): SpecObjectInterface;
    }