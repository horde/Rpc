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
use InvalidArgumentException;

/**
 * JSON-RPC Server error (-32000 to -32099).
 *
 * For implementation-defined server errors within the reserved range.
 */
class ServerErrorException extends HordeRuntimeException implements JsonRpcThrowable
{
    public function __construct(
        string $message = 'Server error',
        int $code = -32000,
        ?\Throwable $previous = null,
        private readonly mixed $errorData = null,
    ) {
        if ($code < -32099 || $code > -32000) {
            throw new InvalidArgumentException(
                "Server error code must be between -32099 and -32000, got $code"
            );
        }
        parent::__construct($message, $code, $previous);
    }

    public function getJsonRpcCode(): int
    {
        return $this->getCode();
    }

    public function getErrorData(): mixed
    {
        return $this->errorData;
    }
}
