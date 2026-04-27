<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\Dispatch;

use Horde\Rpc\Dispatch\MethodDescriptor;
use Horde\Rpc\JsonRpc\Dispatch\Dispatcher;
use Horde\Rpc\JsonRpc\Dispatch\MathApiProvider;
use Horde\Rpc\JsonRpc\Exception\InvalidParamsException;
use Horde\Rpc\JsonRpc\Protocol\Codec;
use Horde\Rpc\JsonRpc\Protocol\Request;
use Horde\Rpc\JsonRpc\Protocol\Response;
use Horde\Rpc\JsonRpc\Protocol\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MathApiProvider::class)]
class MathApiProviderTest extends TestCase
{
    private MathApiProvider $provider;

    protected function setUp(): void
    {
        $this->provider = MathApiProvider::create();
    }

    // --- Method registration ---

    public function testHasMathMethods(): void
    {
        $this->assertTrue($this->provider->hasMethod('math.add'));
        $this->assertTrue($this->provider->hasMethod('math.subtract'));
        $this->assertTrue($this->provider->hasMethod('math.multiply'));
        $this->assertTrue($this->provider->hasMethod('math.divide'));
        $this->assertFalse($this->provider->hasMethod('math.nope'));
    }

    // --- Arithmetic ---

    public function testAdd(): void
    {
        $this->assertSame(7.0, $this->provider->invoke('math.add', [3.0, 4.0])->value);
    }

    public function testSubtract(): void
    {
        $this->assertSame(6.0, $this->provider->invoke('math.subtract', [10.0, 4.0])->value);
    }

    public function testMultiply(): void
    {
        $this->assertSame(12.0, $this->provider->invoke('math.multiply', [3.0, 4.0])->value);
    }

    public function testDivide(): void
    {
        $this->assertSame(2.5, $this->provider->invoke('math.divide', [5.0, 2.0])->value);
    }

    public function testDivideByZero(): void
    {
        $this->expectException(InvalidParamsException::class);
        $this->expectExceptionMessage('Division by zero');
        $this->provider->invoke('math.divide', [1.0, 0.0]);
    }

    // --- Descriptors ---

    public function testListMethodsReturnsFourDescriptors(): void
    {
        $methods = $this->provider->listMethods();
        $this->assertCount(4, $methods);
        $names = array_map(fn(MethodDescriptor $d) => $d->name, $methods);
        $this->assertContains('math.add', $names);
        $this->assertContains('math.subtract', $names);
        $this->assertContains('math.multiply', $names);
        $this->assertContains('math.divide', $names);
    }

    public function testDescriptorsHaveParameterMetadata(): void
    {
        $desc = $this->provider->getMethodDescriptor('math.add');
        $this->assertNotNull($desc);
        $this->assertSame('Add two numbers', $desc->description);
        $this->assertSame('float', $desc->returnType);
        $this->assertCount(2, $desc->parameters);
        $this->assertSame('a', $desc->parameters[0]['name']);
        $this->assertSame('float', $desc->parameters[0]['type']);
        $this->assertTrue($desc->parameters[0]['required']);
    }

    // --- Integration with Dispatcher ---

    public function testDispatcherRoundTrip(): void
    {
        $dispatcher = new Dispatcher($this->provider, $this->provider);
        $request = new Request(Version::V2_0, 'math.multiply', [6.0, 7.0], 1);

        $result = $dispatcher->dispatch($request);

        $this->assertSame(42.0, $result->value);
    }

    public function testRpcDiscoverIncludesMathMethods(): void
    {
        $dispatcher = new Dispatcher($this->provider, $this->provider);
        $request = new Request(Version::V2_0, 'rpc.discover', [], 1);

        $result = $dispatcher->dispatch($request);

        $names = array_column($result->value['methods'], 'name');
        $this->assertContains('math.add', $names);
        $this->assertContains('math.divide', $names);
    }

    // --- Codec round-trip ---

    public function testCodecRoundTrip(): void
    {
        $codec = new Codec();
        $dispatcher = new Dispatcher($this->provider, $this->provider);

        $decoded = $codec->decode('{"jsonrpc":"2.0","method":"math.add","params":[1.5,2.5],"id":1}');
        $this->assertInstanceOf(Request::class, $decoded);

        $result = $dispatcher->dispatch($decoded);
        $response = new Response($decoded->version, $result->value, $decoded->id);
        $json = $codec->encodeResponse($response);

        $payload = json_decode($json, true);
        $this->assertEquals(4.0, $payload['result']);
        $this->assertSame(1, $payload['id']);
    }
}
