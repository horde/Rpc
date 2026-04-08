<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\Dispatch;

use Horde\Rpc\JsonRpc\Dispatch\MethodDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MethodDescriptor::class)]
class MethodDescriptorTest extends TestCase
{
    public function testFullConstruction(): void
    {
        $params = [
            ['name' => 'x', 'type' => 'int', 'required' => true, 'description' => 'First number'],
        ];
        $descriptor = new MethodDescriptor('math.add', 'Add two numbers', $params, 'int');

        $this->assertSame('math.add', $descriptor->name);
        $this->assertSame('Add two numbers', $descriptor->description);
        $this->assertSame($params, $descriptor->parameters);
        $this->assertSame('int', $descriptor->returnType);
    }

    public function testDefaults(): void
    {
        $descriptor = new MethodDescriptor('test');
        $this->assertSame('', $descriptor->description);
        $this->assertSame([], $descriptor->parameters);
        $this->assertNull($descriptor->returnType);
    }
}
