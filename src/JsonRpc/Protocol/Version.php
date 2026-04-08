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
 * JSON-RPC protocol version.
 */
enum Version: string
{
    case V1_1 = '1.1';
    case V2_0 = '2.0';
}
