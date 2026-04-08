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
 * Emitted when a JSON-RPC notification (request without id) is received.
 */
final readonly class NotificationReceived
{
    public function __construct(
        public Request $request,
    ) {}
}
