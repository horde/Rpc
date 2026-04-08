<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\TestDouble;

use Horde\Rpc\JsonRpc\Dispatch\ApiProviderInterface;
use Horde\Rpc\JsonRpc\Dispatch\MethodDescriptor;
use Horde\Rpc\JsonRpc\Dispatch\MethodInvokerInterface;
use Horde\Rpc\JsonRpc\Dispatch\Result;

/**
 * Simple callable-map implementation for testing.
 */
class CallableMapProvider implements ApiProviderInterface, MethodInvokerInterface
{
    /** @var array<string, callable> */
    private array $methods;

    /** @var array<string, MethodDescriptor> */
    private array $descriptors;

    /**
     * @param array<string, callable> $methods
     */
    public function __construct(array $methods = [])
    {
        $this->methods = $methods;
        $this->descriptors = [];
        foreach ($methods as $name => $callable) {
            $this->descriptors[$name] = new MethodDescriptor($name);
        }
    }

    public function hasMethod(string $method): bool
    {
        return isset($this->methods[$method]);
    }

    public function getMethodDescriptor(string $method): ?MethodDescriptor
    {
        return $this->descriptors[$method] ?? null;
    }

    public function listMethods(): array
    {
        return array_values($this->descriptors);
    }

    public function invoke(string $method, array $params): Result
    {
        return new Result(($this->methods[$method])(...$params));
    }
}
