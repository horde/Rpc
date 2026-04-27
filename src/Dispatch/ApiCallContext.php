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
 * Out-of-band context for an API call.
 *
 * Transports populate this with protocol-specific information (identity,
 * auth state, protocol name). Providers may inspect it to filter discovery
 * results or adjust behavior. Null context means "vanilla response."
 */
final readonly class ApiCallContext
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        private array $attributes = [],
    ) {}

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, mixed $value): self
    {
        return new self(array_merge($this->attributes, [$name => $value]));
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
