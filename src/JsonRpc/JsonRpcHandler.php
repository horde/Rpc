<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc;

use Horde\Rpc\Dispatch\ApiProviderInterface;
use Horde\Rpc\Dispatch\MethodInvokerInterface;
use Horde\Rpc\JsonRpc\Dispatch\Dispatcher;
use Horde\Rpc\JsonRpc\Protocol\Codec;
use Horde\Rpc\JsonRpc\Transport\HttpHandler;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;

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
        string $path = '/rpc/jsonrpc',
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
            $path,
        );
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
