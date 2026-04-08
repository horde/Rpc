<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc\Exception;

use Horde\Exception\HordeRuntimeException;
use Horde\Rpc\JsonRpc\Protocol\ErrorCode;

/**
 * JSON-RPC Method not found (-32601).
 *
 * Thrown when the requested method is not available.
 */
class MethodNotFoundException extends HordeRuntimeException implements JsonRpcThrowable
{
    public function __construct(
        string $method,
        int $code = ErrorCode::MethodNotFound->value,
        ?\Throwable $previous = null,
        private readonly mixed $errorData = null,
    ) {
        parent::__construct("Method not found: $method", $code, $previous);
    }

    public function getJsonRpcCode(): int
    {
        return ErrorCode::MethodNotFound->value;
    }

    public function getErrorData(): mixed
    {
        return $this->errorData;
    }
}
