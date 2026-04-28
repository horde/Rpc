<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\TestDouble;

use Horde\Rpc\Dispatch\ApiCallContext;
use Horde\Rpc\Dispatch\ApiProvider;
use Horde\Rpc\Dispatch\MethodDescriptor;
use Horde\Rpc\Dispatch\MethodInvoker;
use Horde\Rpc\Dispatch\Result;

/**
 * Simple callable-map implementation for testing.
 */
class CallableMapProvider implements ApiProvider, MethodInvoker
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
