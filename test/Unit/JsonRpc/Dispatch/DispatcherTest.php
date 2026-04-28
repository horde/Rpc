<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\Dispatch;

use Horde\Rpc\Dispatch\ApiCallContext;
use Horde\Rpc\Dispatch\ApiProvider;
use Horde\Rpc\Dispatch\MethodDescriptor;
use Horde\Rpc\Dispatch\MethodInvoker;
use Horde\Rpc\Dispatch\Result;
use Horde\Rpc\JsonRpc\Dispatch\Dispatcher;
use Horde\Rpc\JsonRpc\Exception\InternalErrorException;
use Horde\Rpc\JsonRpc\Exception\InvalidParamsException;
use Horde\Rpc\JsonRpc\Exception\MethodNotFoundException;
use Horde\Rpc\JsonRpc\Protocol\Request;
use Horde\Rpc\JsonRpc\Protocol\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dispatcher::class)]
class DispatcherTest extends TestCase
{
    private function makeRequest(string $method, array $params = [], int $id = 1): Request
    {
        return new Request(Version::V2_0, $method, $params, $id);
    }

    private function makeDispatcher(
        array $methods = [],
        ?MethodInvoker $invoker = null,
    ): Dispatcher {
        $descriptors = [];
        foreach ($methods as $name => $callable) {
            $descriptors[$name] = new MethodDescriptor($name);
        }

        $provider = new class ($descriptors) implements ApiProvider {
            public function __construct(
                /** @var array<string, MethodDescriptor> */
                private readonly array $descriptors,
            ) {}

            public function hasMethod(string $method, ?ApiCallContext $context = null): bool
            {
                return isset($this->descriptors[$method]);
            }

            public function getMethodDescriptor(string $method, ?ApiCallContext $context = null): ?MethodDescriptor
            {
                return $this->descriptors[$method] ?? null;
            }

            public function listMethods(?ApiCallContext $context = null): array
            {
                return array_values($this->descriptors);
            }
        };

        $invoker ??= new class ($methods) implements MethodInvoker {
            public function __construct(
                /** @var array<string, callable> */
                private readonly array $methods,
            ) {}

            public function invoke(string $method, array $params, ?ApiCallContext $context = null): Result
            {
                return new Result(($this->methods[$method])(...$params));
            }
        };

        return new Dispatcher($provider, $invoker);
    }

    public function testDispatchToProvider(): void
    {
        $dispatcher = $this->makeDispatcher([
            'math.add' => fn(int $a, int $b) => $a + $b,
        ]);

        $result = $dispatcher->dispatch($this->makeRequest('math.add', [3, 4]));
        $this->assertSame(7, $result->value);
    }

    public function testMethodNotFound(): void
    {
        $dispatcher = $this->makeDispatcher([]);

        $this->expectException(MethodNotFoundException::class);
        $dispatcher->dispatch($this->makeRequest('nonexistent'));
    }

    public function testRpcPingBuiltIn(): void
    {
        $dispatcher = $this->makeDispatcher([]);
        $result = $dispatcher->dispatch($this->makeRequest('rpc.ping'));
        $this->assertSame('pong', $result->value);
    }

    public function testRpcDiscoverBuiltIn(): void
    {
        $dispatcher = $this->makeDispatcher([
            'math.add' => fn() => null,
            'math.sub' => fn() => null,
        ]);

        $result = $dispatcher->dispatch($this->makeRequest('rpc.discover'));
        $this->assertIsArray($result->value);
        $this->assertArrayHasKey('methods', $result->value);
        $this->assertCount(2, $result->value['methods']);
        $this->assertSame('math.add', $result->value['methods'][0]['name']);
        $this->assertSame('math.sub', $result->value['methods'][1]['name']);
    }

    public function testProviderWinsOverBuiltInPing(): void
    {
        $dispatcher = $this->makeDispatcher([
            'rpc.ping' => fn() => 'custom-pong',
        ]);

        $result = $dispatcher->dispatch($this->makeRequest('rpc.ping'));
        $this->assertSame('custom-pong', $result->value);
    }

    public function testProviderWinsOverBuiltInDiscover(): void
    {
        $dispatcher = $this->makeDispatcher([
            'rpc.discover' => fn() => ['custom' => true],
        ]);

        $result = $dispatcher->dispatch($this->makeRequest('rpc.discover'));
        $this->assertSame(['custom' => true], $result->value);
    }

    public function testInvokerExceptionPropagates(): void
    {
        $invoker = new class implements MethodInvoker {
            public function invoke(string $method, array $params, ?ApiCallContext $context = null): Result
            {
                throw new InvalidParamsException('Bad params');
            }
        };

        $dispatcher = $this->makeDispatcher(
            ['test' => fn() => null],
            $invoker,
        );

        $this->expectException(InvalidParamsException::class);
        $dispatcher->dispatch($this->makeRequest('test'));
    }

    public function testInvokerInternalErrorPropagates(): void
    {
        $invoker = new class implements MethodInvoker {
            public function invoke(string $method, array $params, ?ApiCallContext $context = null): Result
            {
                throw new InternalErrorException('Something broke');
            }
        };

        $dispatcher = $this->makeDispatcher(
            ['test' => fn() => null],
            $invoker,
        );

        $this->expectException(InternalErrorException::class);
        $dispatcher->dispatch($this->makeRequest('test'));
    }
}
