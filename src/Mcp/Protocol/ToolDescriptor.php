<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Mcp\Protocol;

use Horde\Rpc\Dispatch\MethodDescriptor;

/**
 * MCP tool descriptor, bridges from MethodDescriptor to MCP tool format.
 */
final readonly class ToolDescriptor
{
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema,
        public ?array $outputSchema = null,
    ) {}

    /**
     * Build from a MethodDescriptor.
     *
     * If the descriptor has an explicit inputSchema, use it directly.
     * Otherwise, auto-generate a JSON Schema from the parameters array.
     */
    public static function fromMethodDescriptor(MethodDescriptor $desc): self
    {
        $inputSchema = $desc->inputSchema ?? self::buildInputSchema($desc->parameters);

        return new self(
            $desc->name,
            $desc->description,
            $inputSchema,
            $desc->outputSchema,
        );
    }

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema,
        ];
        if ($this->outputSchema !== null) {
            $data['outputSchema'] = $this->outputSchema;
        }

        return $data;
    }

    /**
     * Auto-generate a JSON Schema from a parameters array.
     *
     * Each parameter entry is expected to have: name, type, required (optional).
     */
    private static function buildInputSchema(array $parameters): array
    {
        if (empty($parameters)) {
            return ['type' => 'object', 'additionalProperties' => false];
        }

        $properties = [];
        $required = [];
        foreach ($parameters as $param) {
            $name = $param['name'];
            $prop = [];
            if (isset($param['type'])) {
                $prop['type'] = self::phpTypeToJsonSchema($param['type']);
            }
            if (isset($param['description'])) {
                $prop['description'] = $param['description'];
            }
            $properties[$name] = $prop;
            if (!empty($param['required'])) {
                $required[] = $name;
            }
        }

        $schema = ['type' => 'object', 'properties' => $properties];
        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    private static function phpTypeToJsonSchema(string $type): string
    {
        return match ($type) {
            'int', 'integer' => 'integer',
            'float', 'double', 'number' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            'string' => 'string',
            default => 'string',
        };
    }
}
