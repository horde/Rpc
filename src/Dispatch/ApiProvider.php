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
 */
interface ApiProvider
{
    /**
     * Check if a method is available.
     *
     * When a context is provided, the provider may use it to filter
     * availability (e.g. hiding methods from unauthenticated callers).
     */
    public function hasMethod(string $method, ?ApiCallContext $context = null): bool;

    /**
     * Get method descriptor for introspection/validation.
     *
     * Returns null if the method does not exist or is not visible
     * in the given context.
     */
    public function getMethodDescriptor(string $method, ?ApiCallContext $context = null): ?MethodDescriptor;

    /**
     * List all available methods.
     *
     * When a context is provided, the provider may filter the returned
     * list (e.g. omitting methods that require authentication when the
     * caller is anonymous).
     *
     * @return list<MethodDescriptor>
     */
    public function listMethods(?ApiCallContext $context = null): array;
}
