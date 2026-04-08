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
 * MCP resource content, returned by resources/read.
 */
final readonly class ResourceContent
{
    public function __construct(
        public string $uri,
        public ?string $text = null,
        public ?string $blob = null,
        public ?string $mimeType = null,
    ) {}

    public function toArray(): array
    {
        $data = ['uri' => $this->uri];
        if ($this->mimeType !== null) {
            $data['mimeType'] = $this->mimeType;
        }
        if ($this->text !== null) {
            $data['text'] = $this->text;
        }
        if ($this->blob !== null) {
            $data['blob'] = $this->blob;
        }

        return $data;
    }
}
