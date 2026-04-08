<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Mcp;

use Horde\Rpc\Mcp\Protocol\ResourceContent;
use Horde\Rpc\Mcp\Protocol\ResourceDescriptor;

/**
 * Provides MCP resources (read-only data for context).
 */
interface ResourceProviderInterface
{
    /**
     * List all available resources.
     *
     * @return list<ResourceDescriptor>
     */
    public function listResources(): array;

    /**
     * Check if a resource exists.
     */
    public function hasResource(string $uri): bool;

    /**
     * Read a resource's content.
     */
    public function readResource(string $uri): ResourceContent;
}
