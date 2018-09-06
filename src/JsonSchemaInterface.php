<?php
    namespace Enobrev\API;

    interface JsonSchemaInterface {
        public function getJsonSchema(): array;
        public function getJsonSchemaForOpenAPI(): array;
    }