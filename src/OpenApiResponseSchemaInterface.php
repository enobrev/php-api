<?php
    namespace Enobrev\API;

    interface OpenApiResponseSchemaInterface {
        public function getOpenAPI(): array;
    }