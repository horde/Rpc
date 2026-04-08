<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc\Exception;

use Horde\Exception\HordeInvalidArgumentException;
use Horde\Rpc\JsonRpc\Protocol\ErrorCode;

/**
 * JSON-RPC Invalid params (-32602).
 *
 * Thrown when method parameters are invalid.
 */
class InvalidParamsException extends HordeInvalidArgumentException implements JsonRpcThrowable
{
    public function __construct(
        string $message = 'Invalid params',
        int $code = ErrorCode::InvalidParams->value,
        ?\Throwable $previous = null,
        private readonly mixed $errorData = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getJsonRpcCode(): int
    {
        return ErrorCode::InvalidParams->value;
    }

    public function getErrorData(): mixed
    {
        return $this->errorData;
    }
}
