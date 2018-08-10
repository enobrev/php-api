<?php
    namespace Enobrev\API;

    interface Validation {
        public function validate($mValue): bool;

        public function errors(): ?array;

        public function requirement(): string;

        public function error_codes(): array;
    }