<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\Mcp;

use Horde\Rpc\Mcp\AuthContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthContext::class)]
class AuthContextTest extends TestCase
{
    public function testAnonymous(): void
    {
        $ctx = AuthContext::anonymous();

        $this->assertFalse($ctx->authenticated);
        $this->assertSame([], $ctx->permissions);
    }

    public function testWithPermissions(): void
    {
        $ctx = AuthContext::withPermissions(['read', 'write']);

        $this->assertTrue($ctx->authenticated);
        $this->assertSame(['read', 'write'], $ctx->permissions);
    }

    public function testHasPermission(): void
    {
        $ctx = AuthContext::withPermissions(['read', 'write']);

        $this->assertTrue($ctx->hasPermission('read'));
        $this->assertTrue($ctx->hasPermission('write'));
        $this->assertFalse($ctx->hasPermission('admin'));
    }

    public function testHasAllPermissions(): void
    {
        $ctx = AuthContext::withPermissions(['read', 'write', 'admin']);

        $this->assertTrue($ctx->hasAllPermissions(['read', 'write']));
        $this->assertTrue($ctx->hasAllPermissions([]));
        $this->assertFalse($ctx->hasAllPermissions(['read', 'delete']));
    }

    public function testAnonymousHasNoPermissions(): void
    {
        $ctx = AuthContext::anonymous();

        $this->assertFalse($ctx->hasPermission('anything'));
        $this->assertTrue($ctx->hasAllPermissions([]));
    }
}
