<?php
    declare(strict_types = 1);

    namespace Enobrev\API\Middleware;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\Method;

    use function Enobrev\dbg;
    use Zend\Diactoros\Response;

    /**
     * @package Enobrev\API\Middleware
     */
    class OptionsHandler implements MiddlewareInterface {

        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            dbg($oRequest->getUri()->getPath());
            exit();

            return (new Response());
        }
    }
