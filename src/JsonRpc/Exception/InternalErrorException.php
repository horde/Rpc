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
use Throwable;

/**
 * JSON-RPC Internal error (-32603).
 *
 * Thrown for internal server errors during method invocation.
 */
class InternalErrorException extends HordeRuntimeException implements JsonRpcThrowable
{
    public function __construct(
        string $message = 'Internal error',
        int $code = ErrorCode::InternalError->value,
        ?Throwable $previous = null,
        private readonly mixed $errorData = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getJsonRpcCode(): int
    {
        return ErrorCode::InternalError->value;
    }

    public function getErrorData(): mixed
    {
        return $this->errorData;
    }
}
