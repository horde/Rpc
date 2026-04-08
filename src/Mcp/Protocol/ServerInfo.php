<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Mcp\Protocol;

/**
 * MCP server identity, sent during initialization.
 */
final readonly class ServerInfo
{
    public function __construct(
        public string $name,
        public string $version,
        public string $description = '',
    ) {}

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'version' => $this->version,
        ];
        if ($this->description !== '') {
            $data['description'] = $this->description;
        }

        return $data;
    }
}
