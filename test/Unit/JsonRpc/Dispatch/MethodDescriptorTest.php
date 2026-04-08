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
        $this->assertNull($descriptor->inputSchema);
        $this->assertNull($descriptor->outputSchema);
        $this->assertSame([], $descriptor->permissions);
    }

    public function testInputSchemaAndOutputSchema(): void
    {
        $inputSchema = [
            'type' => 'object',
            'properties' => ['a' => ['type' => 'number']],
            'required' => ['a'],
        ];
        $outputSchema = [
            'type' => 'object',
            'properties' => ['result' => ['type' => 'number']],
        ];
        $descriptor = new MethodDescriptor(
            'calc',
            inputSchema: $inputSchema,
            outputSchema: $outputSchema,
        );

        $this->assertSame($inputSchema, $descriptor->inputSchema);
        $this->assertSame($outputSchema, $descriptor->outputSchema);
    }

    public function testPermissions(): void
    {
        $descriptor = new MethodDescriptor(
            'admin.reset',
            permissions: ['horde:admin'],
        );

        $this->assertSame(['horde:admin'], $descriptor->permissions);
    }

    public function testEmptyPermissionsMeansPublic(): void
    {
        $descriptor = new MethodDescriptor('public.method');
        $this->assertSame([], $descriptor->permissions);
    }
}
