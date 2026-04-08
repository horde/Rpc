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
 * A JSON-RPC success response.
 */
final readonly class Response
{
    public function __construct(
        public Version $version,
        public mixed $result,
        public string|int $id,
    ) {}
}
