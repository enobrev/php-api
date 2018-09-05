<?php
    namespace Enobrev\API\FullSpec;

    interface ComponentListInterface {
        /** @return ComponentInterface[] */
        public function components(): array;
    }