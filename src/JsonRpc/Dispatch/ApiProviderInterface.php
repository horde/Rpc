<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc\Dispatch;

/**
 * Provides the list of available JSON-RPC methods.
 *
 * Decouples method registration from the JSON-RPC protocol layer.
 * Implementations may wrap a Horde registry, a simple array map,
 * a PSR-11 container, or any other method source.
 */
interface ApiProviderInterface
{
    /**
     * Check if a method is available.
     */
    public function hasMethod(string $method): bool;

    /**
     * Get method descriptor for introspection/validation.
     *
     * Returns null if the method does not exist.
     */
    public function getMethodDescriptor(string $method): ?MethodDescriptor;

    /**
     * List all available methods.
     *
     * @return list<MethodDescriptor>
     */
    public function listMethods(): array;
}
