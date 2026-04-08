<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc;

use Horde\Rpc\JsonRpc\Dispatch\ApiProviderInterface;
use Horde\Rpc\JsonRpc\Dispatch\Dispatcher;
use Horde\Rpc\JsonRpc\Dispatch\MethodInvokerInterface;
use Horde\Rpc\JsonRpc\Protocol\Codec;
use Horde\Rpc\JsonRpc\Transport\HttpHandler;
use Horde\Rpc\JsonRpc\Transport\Middleware\JsonRpcMiddleware;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Convenience facade that wires all JSON-RPC components together.
 *
 * Construct with your provider, invoker, and PSR factories, then
 * call getHandler() or getMiddleware() for integration.
 */
final class JsonRpcHandler
{
    private readonly HttpHandler $httpHandler;

    public function __construct(
        ApiProviderInterface $provider,
        MethodInvokerInterface $invoker,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        EventDispatcherInterface $eventDispatcher,
        int $maxBatchSize = 100,
    ) {
        $codec = new Codec();
        $dispatcher = new Dispatcher($provider, $invoker);

        $this->httpHandler = new HttpHandler(
            $codec,
            $dispatcher,
            $responseFactory,
            $streamFactory,
            $eventDispatcher,
            $maxBatchSize,
        );
    }

    /**
     * Get the PSR-15 request handler for direct use.
     */
    public function getHandler(): HttpHandler
    {
        return $this->httpHandler;
    }

    /**
     * Get a PSR-15 middleware that routes matching requests to the handler.
     */
    public function getMiddleware(string $path = '/rpc/jsonrpc'): JsonRpcMiddleware
    {
        return new JsonRpcMiddleware($this->httpHandler, $path);
    }
}
