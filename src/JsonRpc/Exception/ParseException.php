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
 * JSON-RPC Parse error (-32700).
 *
 * Thrown when the server receives invalid JSON.
 */
class ParseException extends HordeRuntimeException implements JsonRpcThrowable
{
    public function __construct(
        string $message = 'Parse error',
        int $code = ErrorCode::ParseError->value,
        ?\Throwable $previous = null,
        private readonly mixed $errorData = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getJsonRpcCode(): int
    {
        return ErrorCode::ParseError->value;
    }

    public function getErrorData(): mixed
    {
        return $this->errorData;
    }
}
