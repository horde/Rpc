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
 * Metadata describing an available API method.
 */
final readonly class MethodDescriptor
{
    /**
     * @param string $name Method name (dot-separated, e.g. "calendar.list")
     * @param string $description Human-readable description
     * @param array $parameters Parameter descriptors (name, type, required, description)
     * @param ?string $returnType Return type description
     * @param ?array $inputSchema JSON Schema for MCP tool input (auto-generated from $parameters if null)
     * @param ?array $outputSchema JSON Schema for MCP tool output (optional)
     * @param array $permissions Required permissions (empty = public, like Horde's $_noPerms)
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public array $parameters = [],
        public ?string $returnType = null,
        public ?array $inputSchema = null,
        public ?array $outputSchema = null,
        public array $permissions = [],
    ) {}
}
