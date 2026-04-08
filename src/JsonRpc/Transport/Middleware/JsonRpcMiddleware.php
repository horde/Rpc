<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc\Transport\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware that routes JSON-RPC requests to the HttpHandler.
 *
 * Matches: POST + configured path + JSON content type.
 * Non-matching requests are passed to the next handler.
 */
final class JsonRpcMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RequestHandlerInterface $handler,
        private readonly string $path = '/rpc/jsonrpc',
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $next,
    ): ResponseInterface {
        if ($this->matches($request)) {
            return $this->handler->handle($request);
        }

        return $next->handle($request);
    }

    private function matches(ServerRequestInterface $request): bool
    {
        if ($request->getMethod() !== 'POST') {
            return false;
        }

        if ($request->getUri()->getPath() !== $this->path) {
            return false;
        }

        $contentType = $request->getHeaderLine('Content-Type');

        return str_contains($contentType, 'json');
    }
}
