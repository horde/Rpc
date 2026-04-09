<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Mcp;

use RuntimeException;

/**
 * MCP protocol error, maps to a JSON-RPC error response.
 */
final class McpError extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $jsonRpcCode,
        private readonly mixed $errorData = null,
    ) {
        parent::__construct($message, $jsonRpcCode);
    }

    public function getJsonRpcCode(): int
    {
        return $this->jsonRpcCode;
    }

    public function getErrorData(): mixed
    {
        return $this->errorData;
    }

    public static function methodNotFound(string $method): self
    {
        return new self("Method not found: $method", -32601);
    }

    public static function toolNotFound(string $name): self
    {
        return new self("Unknown tool: $name", -32602);
    }

    public static function resourceNotFound(string $uri): self
    {
        return new self("Resource not found", -32002, ['uri' => $uri]);
    }

    public static function unauthorized(string $method): self
    {
        return new self("Unauthorized: $method requires authentication", -32603);
    }

    public static function invalidRequest(string $message): self
    {
        return new self($message, -32600);
    }
}
