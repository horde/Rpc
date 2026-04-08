<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Mcp;

use Horde\Rpc\JsonRpc\Dispatch\ApiProviderInterface;
use Horde\Rpc\JsonRpc\Dispatch\MethodInvokerInterface;
use Horde\Rpc\Mcp\Protocol\ServerCapabilities;
use Horde\Rpc\Mcp\Protocol\ServerInfo;
use Horde\Rpc\Mcp\Transport\HttpHandler;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Convenience facade that wires all MCP server components together.
 *
 * Example usage:
 *
 *     $server = new McpServer(
 *         new ServerInfo('my-app', '1.0.0'),
 *         $provider, $provider,
 *         new ResponseFactory(), new StreamFactory(),
 *     );
 *     $handler = $server->getHandler();
 */
final class McpServer
{
    private readonly HttpHandler $httpHandler;

    public function __construct(
        ServerInfo $serverInfo,
        ApiProviderInterface $provider,
        MethodInvokerInterface $invoker,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        ?ResourceProviderInterface $resourceProvider = null,
        string $path = '/mcp',
    ) {
        $capabilities = new ServerCapabilities(
            tools: true,
            resources: $resourceProvider !== null,
        );

        $router = new McpRouter(
            $serverInfo,
            $capabilities,
            $provider,
            $invoker,
            $resourceProvider,
        );

        $this->httpHandler = new HttpHandler($router, $responseFactory, $streamFactory, $path);
    }

    /**
     * Get the handler for direct use or as middleware.
     *
     * The returned object implements both RequestHandlerInterface and
     * MiddlewareInterface, so it can be used directly or piped into
     * a middleware stack.
     */
    public function getHandler(): HttpHandler
    {
        return $this->httpHandler;
    }

    /**
     * Get the handler typed as MiddlewareInterface for middleware stacks.
     *
     * Returns the same object as getHandler(). Provided for readability
     * when the caller only needs the middleware interface.
     */
    public function getMiddleware(): MiddlewareInterface
    {
        return $this->httpHandler;
    }
}
