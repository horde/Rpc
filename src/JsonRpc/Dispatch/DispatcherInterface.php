<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc\Dispatch;

use Horde\Rpc\Dispatch\Result;
use Horde\Rpc\JsonRpc\Protocol\Request;

/**
 * Resolves and dispatches a JSON-RPC request to the appropriate method.
 */
interface DispatcherInterface
{
    /**
     * Dispatch a JSON-RPC request.
     *
     * @throws \Horde\Rpc\JsonRpc\Exception\MethodNotFoundException If method is not registered
     * @throws \Horde\Rpc\JsonRpc\Exception\InvalidParamsException If parameters are invalid
     */
    public function dispatch(Request $request): Result;
}
