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
 * Executes a method by name with given parameters.
 *
 * Separates "what to call" from "how to call it". Implementations
 * may wrap a Horde registry, a callable map, or a PSR-11 container.
 *
 * @deprecated Use MethodInvoker instead.
 */
interface MethodInvokerInterface extends MethodInvoker {}
