<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc\Event;

use Horde\Rpc\JsonRpc\Protocol\Request;

/**
 * Emitted after a JSON-RPC method is successfully invoked.
 */
final readonly class RequestDispatched
{
    public function __construct(
        public Request $request,
        public mixed $result,
        public float $duration,
    ) {}
}
