<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\Dispatch;

use Horde\Rpc\JsonRpc\Dispatch\CallableMapProvider;
use Horde\Rpc\JsonRpc\Dispatch\MethodDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CallableMapProvider::class)]
class CallableMapProviderTest extends TestCase
{
    public function testHasMethod(): void
    {
        $provider = new CallableMapProvider([
            'test' => fn() => null,
        ]);

        $this->assertTrue($provider->hasMethod('test'));
        $this->assertFalse($provider->hasMethod('nope'));
    }

    public function testInvoke(): void
    {
        $provider = new CallableMapProvider([
            'add' => fn(int $a, int $b) => $a + $b,
        ]);

        $result = $provider->invoke('add', [3, 4]);
        $this->assertSame(7, $result->value);
    }

    public function testListMethodsAutoDescriptors(): void
    {
        $provider = new CallableMapProvider([
            'a' => fn() => null,
            'b' => fn() => null,
        ]);

        $methods = $provider->listMethods();
        $this->assertCount(2, $methods);
        $this->assertSame('a', $methods[0]->name);
        $this->assertSame('b', $methods[1]->name);
        $this->assertSame('', $methods[0]->description);
    }

    public function testExplicitDescriptors(): void
    {
        $desc = new MethodDescriptor('greet', 'Say hello', [], 'string');
        $provider = new CallableMapProvider(
            ['greet' => fn(string $name) => "Hello $name"],
            ['greet' => $desc],
        );

        $this->assertSame($desc, $provider->getMethodDescriptor('greet'));
        $this->assertSame('Say hello', $provider->listMethods()[0]->description);
    }

    public function testGetMethodDescriptorReturnsNullForUnknown(): void
    {
        $provider = new CallableMapProvider([]);
        $this->assertNull($provider->getMethodDescriptor('nope'));
    }

    public function testMixedExplicitAndAutoDescriptors(): void
    {
        $desc = new MethodDescriptor('a', 'Described');
        $provider = new CallableMapProvider(
            ['a' => fn() => 1, 'b' => fn() => 2],
            ['a' => $desc],
        );

        $this->assertSame('Described', $provider->getMethodDescriptor('a')->description);
        $this->assertSame('', $provider->getMethodDescriptor('b')->description);
    }
}
