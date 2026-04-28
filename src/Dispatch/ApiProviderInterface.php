<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Dispatch;

/**
 * Provides the list of available API methods.
 *
 * Decouples method registration from any specific protocol layer.
 * Implementations may wrap a Horde registry, a simple array map,
 * a PSR-11 container, or any other method source.
 *
 * @deprecated Use ApiProvider instead.
 */
interface ApiProviderInterface extends ApiProvider
{
}
