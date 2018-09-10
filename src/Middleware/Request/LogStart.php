<?php
    namespace Enobrev\API\Middleware\Request;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\Middleware\FastRoute;
    use Enobrev\API\RequestAttribute;
    use Enobrev\API\RequestAttributeInterface;
    use Enobrev\API\Spec;
    use Enobrev\API\SpecInterface;
    use Enobrev\Log;

    use function Enobrev\dbg;

    class LogStart implements MiddlewareInterface {
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            Log::i('Enobrev.Middleware.LogStart', [
                '#request' => [
                    'method'     => $oRequest->getMethod(),
                    'path'       => $oRequest->getUri()->getPath(),
                    'query'      => json_encode($oRequest->getQueryParams()),
                    'headers'    => json_encode($oRequest->getHeaders()),
                    'post'       => json_encode($oRequest->getParsedBody())
                ]
            ]);

            return $oHandler->handle($oRequest);
        }
    }