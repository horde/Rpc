<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc\Event;

use Horde\Rpc\JsonRpc\Protocol\Error;
use Horde\Rpc\JsonRpc\Protocol\Request;
use Throwable;

/**
 * Emitted when a JSON-RPC error occurs during processing.
 */
final readonly class ErrorOccurred
{
    public function __construct(
        public Error $error,
        public ?Throwable $cause = null,
        public ?Request $request = null,
    ) {}
}
