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
 * MCP resource metadata, returned by resources/list.
 */
final readonly class ResourceDescriptor
{
    public function __construct(
        public string $uri,
        public string $name,
        public string $description = '',
        public ?string $mimeType = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'uri' => $this->uri,
            'name' => $this->name,
        ];
        if ($this->description !== '') {
            $data['description'] = $this->description;
        }
        if ($this->mimeType !== null) {
            $data['mimeType'] = $this->mimeType;
        }

        return $data;
    }
}
