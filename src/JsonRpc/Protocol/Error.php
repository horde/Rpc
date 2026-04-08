<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc\Protocol;

/**
 * A JSON-RPC error response.
 */
final readonly class Error
{
    public function __construct(
        public Version $version,
        public ErrorCode|int $code,
        public string $message,
        public mixed $data = null,
        public string|int|null $id = null,
    ) {}
}
