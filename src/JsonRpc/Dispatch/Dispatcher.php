<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc\Dispatch;

use Horde\Rpc\Dispatch\ApiProviderInterface;
use Horde\Rpc\Dispatch\MethodInvokerInterface;
use Horde\Rpc\Dispatch\Result;
use Horde\Rpc\JsonRpc\Exception\MethodNotFoundException;
use Horde\Rpc\JsonRpc\Protocol\Request;

/**
 * Default dispatcher with built-in system method fallbacks.
 *
 * Resolution order:
 * 1. Provider claims the method → delegate to invoker
 * 2. Built-in system method (rpc.*) → handle internally
 * 3. Neither → MethodNotFoundException
 *
 * The provider always wins: if it claims rpc.discover or rpc.ping,
 * the built-in is not used.
 */
final class Dispatcher implements DispatcherInterface
{
    private const SYSTEM_METHODS = ['rpc.discover', 'rpc.ping'];

    public function __construct(
        private readonly ApiProviderInterface $provider,
        private readonly MethodInvokerInterface $invoker,
    ) {}

    public function dispatch(Request $request): Result
    {
        // Provider-wins: check provider first
        if ($this->provider->hasMethod($request->method)) {
            return $this->invoker->invoke($request->method, $request->params);
        }

        // Built-in system methods as fallback
        if (in_array($request->method, self::SYSTEM_METHODS, true)) {
            return $this->handleSystemMethod($request->method);
        }

        throw new MethodNotFoundException($request->method);
    }

    private function handleSystemMethod(string $method): Result
    {
        return match ($method) {
            'rpc.discover' => new Result($this->buildServiceDescription()),
            'rpc.ping' => new Result('pong'),
        };
    }

    private function buildServiceDescription(): array
    {
        $methods = [];
        foreach ($this->provider->listMethods() as $descriptor) {
            $methods[] = [
                'name' => $descriptor->name,
                'description' => $descriptor->description,
                'parameters' => $descriptor->parameters,
                'returnType' => $descriptor->returnType,
            ];
        }

        return ['methods' => $methods];
    }
}
