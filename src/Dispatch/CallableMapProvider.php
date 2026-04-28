<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Dispatch;

/**
 * Generic provider backed by a callable map.
 *
 * Each method is a name-callable pair. Implements both ApiProvider
 * (method registry) and MethodInvoker (method execution), making it
 * a convenient all-in-one for simple APIs.
 *
 * Example usage:
 *
 *     $provider = new CallableMapProvider([
 *         'math.add' => fn(float $a, float $b): float => $a + $b,
 *         'ping'     => fn(): string => 'pong',
 *     ]);
 */
final class CallableMapProvider implements ApiProvider, MethodInvoker
{
    /** @var array<string, callable> */
    private readonly array $methods;

    /** @var array<string, MethodDescriptor> */
    private readonly array $descriptors;

    /**
     * @param array<string, callable> $methods Name-callable map
     * @param array<string, MethodDescriptor> $descriptors Optional descriptors keyed by method name.
     *        Methods without an explicit descriptor get a minimal auto-generated one.
     */
    public function __construct(array $methods, array $descriptors = [])
    {
        $this->methods = $methods;

        $merged = [];
        foreach ($methods as $name => $callable) {
            $merged[$name] = $descriptors[$name] ?? new MethodDescriptor($name);
        }
        $this->descriptors = $merged;
    }

    public function hasMethod(string $method, ?ApiCallContext $context = null): bool
    {
        return isset($this->methods[$method]);
    }

    public function getMethodDescriptor(string $method, ?ApiCallContext $context = null): ?MethodDescriptor
    {
        return $this->descriptors[$method] ?? null;
    }

    public function listMethods(?ApiCallContext $context = null): array
    {
        return array_values($this->descriptors);
    }

    public function invoke(string $method, array $params, ?ApiCallContext $context = null): Result
    {
        return new Result(($this->methods[$method])(...$params));
    }
}
