<?php
    namespace Enobrev\API;

    interface FullSpecComponentInterface {
        /** @return FullSpecComponent[] */
        public function components(): array;
    }