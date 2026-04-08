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
 * A parsed JSON-RPC request.
 *
 * Notifications are requests without an id. Use isNotification() to check.
 */
final readonly class Request
{
    public function __construct(
        public Version $version,
        public string $method,
        public array $params,
        public string|int|null $id,
    ) {}

    public function isNotification(): bool
    {
        return $this->id === null;
    }
}
