<?php
    namespace Enobrev\API\Mock\v1;

    use Enobrev\API\Rest;
    use Enobrev\API\Mock\Table;
    use Enobrev\API\Role;

    class User extends Rest  {
        /** @var Table\User|Table\User */
        protected $Data;

        protected $DefaultRole = Role\VIEWER;
    }