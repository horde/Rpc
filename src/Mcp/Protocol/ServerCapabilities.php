<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Mcp\Protocol;

use stdClass;

/**
 * MCP server capabilities, advertised during initialization.
 */
final readonly class ServerCapabilities
{
    public function __construct(
        public bool $tools = false,
        public bool $resources = false,
        public bool $toolsListChanged = false,
        public bool $resourcesListChanged = false,
    ) {}

    public function toArray(): array
    {
        $caps = [];
        if ($this->tools) {
            $toolsCap = [];
            if ($this->toolsListChanged) {
                $toolsCap['listChanged'] = true;
            }
            $caps['tools'] = empty($toolsCap) ? new stdClass() : $toolsCap;
        }
        if ($this->resources) {
            $resCap = [];
            if ($this->resourcesListChanged) {
                $resCap['listChanged'] = true;
            }
            $caps['resources'] = empty($resCap) ? new stdClass() : $resCap;
        }

        return $caps;
    }
}
