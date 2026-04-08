<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc\Event;

use Horde\Rpc\JsonRpc\Protocol\Batch;

/**
 * Emitted before a JSON-RPC 2.0 batch is processed.
 */
final readonly class BatchProcessing
{
    public function __construct(
        public Batch $batch,
        public int $requestCount,
        public int $notificationCount,
    ) {}
}
