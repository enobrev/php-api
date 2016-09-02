<?php
    namespace Enobrev\API\Mock\v1;

    use Enobrev\API\Rest;
    use Enobrev\API\Mock\Table;

    class Address extends Rest  {
        /** @var Table\Address|Table\Address */
        protected $Data;
    }