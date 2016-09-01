<?php
    namespace Enobrev\API\Method;

    const OPTIONS = 'OPTIONS';
    const HEAD    = 'HEAD';
    const GET     = 'GET';
    const POST    = 'POST';
    const PUT     = 'PUT';
    const DELETE  = 'DELETE';

    const _ALL    = [OPTIONS, HEAD, GET, POST, PUT, DELETE];
    const _UPDATE = [OPTIONS, HEAD, GET, PUT, DELETE];
    const _PUBLIC = [OPTIONS, HEAD, GET];
    const _NONE   = [];