<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc\Exception;

use Horde\Exception\HordeThrowable;

/**
 * Marker interface for all JSON-RPC exceptions.
 *
 * Exceptions implementing this interface map directly to JSON-RPC error
 * codes and can be rendered as JSON-RPC error responses by the Codec.
 */
interface JsonRpcThrowable extends HordeThrowable
{
    /**
     * Return the JSON-RPC error code for this exception.
     */
    public function getJsonRpcCode(): int;

    /**
     * Optional structured data to include in the error response "data" field.
     */
    public function getErrorData(): mixed;
}
