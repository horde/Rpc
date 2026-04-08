<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Mcp;

/**
 * Authentication context for MCP requests.
 *
 * Carries the permissions granted to the current user. Created by the
 * transport layer from PSR-7 request attributes (set by upstream auth
 * middleware).
 *
 * Follows Horde's $_noPerms pattern: methods with empty permissions
 * are public; non-empty permissions require all listed permissions.
 */
final readonly class AuthContext
{
    /**
     * @param list<string> $permissions Granted permission identifiers
     * @param bool $authenticated Whether the user is authenticated
     */
    public function __construct(
        public array $permissions = [],
        public bool $authenticated = false,
    ) {}

    public static function anonymous(): self
    {
        return new self();
    }

    /**
     * @param list<string> $permissions Granted permissions
     */
    public static function withPermissions(array $permissions): self
    {
        return new self($permissions, true);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    /**
     * @param list<string> $permissions Required permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }
}
