<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\Mcp\Protocol;

use Horde\Rpc\Dispatch\MethodDescriptor;
use Horde\Rpc\Mcp\Protocol\ToolDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolDescriptor::class)]
class ToolDescriptorTest extends TestCase
{
    public function testFromMethodDescriptorWithExplicitSchema(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => ['a' => ['type' => 'number']],
            'required' => ['a'],
        ];
        $desc = new MethodDescriptor('calc', 'Calculate', inputSchema: $schema);

        $tool = ToolDescriptor::fromMethodDescriptor($desc);

        $this->assertSame('calc', $tool->name);
        $this->assertSame('Calculate', $tool->description);
        $this->assertSame($schema, $tool->inputSchema);
    }

    public function testFromMethodDescriptorAutoGeneratesSchema(): void
    {
        $desc = new MethodDescriptor('math.add', 'Add', [
            ['name' => 'a', 'type' => 'float', 'required' => true],
            ['name' => 'b', 'type' => 'float', 'required' => true],
        ]);

        $tool = ToolDescriptor::fromMethodDescriptor($desc);

        $this->assertSame('object', $tool->inputSchema['type']);
        $this->assertSame('number', $tool->inputSchema['properties']['a']['type']);
        $this->assertSame('number', $tool->inputSchema['properties']['b']['type']);
        $this->assertSame(['a', 'b'], $tool->inputSchema['required']);
    }

    public function testFromMethodDescriptorNoParams(): void
    {
        $desc = new MethodDescriptor('ping');

        $tool = ToolDescriptor::fromMethodDescriptor($desc);

        $this->assertSame(['type' => 'object', 'additionalProperties' => false], $tool->inputSchema);
    }

    public function testFromMethodDescriptorWithOutputSchema(): void
    {
        $output = ['type' => 'object', 'properties' => ['result' => ['type' => 'number']]];
        $desc = new MethodDescriptor('calc', outputSchema: $output);

        $tool = ToolDescriptor::fromMethodDescriptor($desc);

        $this->assertSame($output, $tool->outputSchema);
    }

    public function testToArray(): void
    {
        $tool = new ToolDescriptor(
            'test',
            'A test tool',
            ['type' => 'object'],
        );

        $arr = $tool->toArray();

        $this->assertSame('test', $arr['name']);
        $this->assertSame('A test tool', $arr['description']);
        $this->assertSame(['type' => 'object'], $arr['inputSchema']);
        $this->assertArrayNotHasKey('outputSchema', $arr);
    }

    public function testToArrayWithOutputSchema(): void
    {
        $output = ['type' => 'number'];
        $tool = new ToolDescriptor('test', 'Test', ['type' => 'object'], $output);

        $arr = $tool->toArray();

        $this->assertSame($output, $arr['outputSchema']);
    }

    public function testTypeMapping(): void
    {
        $desc = new MethodDescriptor('types', 'Type test', [
            ['name' => 'i', 'type' => 'int', 'required' => true],
            ['name' => 's', 'type' => 'string', 'required' => false],
            ['name' => 'b', 'type' => 'bool'],
            ['name' => 'a', 'type' => 'array'],
        ]);

        $tool = ToolDescriptor::fromMethodDescriptor($desc);

        $this->assertSame('integer', $tool->inputSchema['properties']['i']['type']);
        $this->assertSame('string', $tool->inputSchema['properties']['s']['type']);
        $this->assertSame('boolean', $tool->inputSchema['properties']['b']['type']);
        $this->assertSame('array', $tool->inputSchema['properties']['a']['type']);
        $this->assertSame(['i'], $tool->inputSchema['required']);
    }
}
