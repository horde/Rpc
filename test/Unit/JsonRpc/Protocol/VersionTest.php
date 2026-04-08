<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\Protocol;

use Horde\Rpc\JsonRpc\Protocol\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Version::class)]
class VersionTest extends TestCase
{
    public function testV20FromString(): void
    {
        $this->assertSame(Version::V2_0, Version::from('2.0'));
    }

    public function testV11FromString(): void
    {
        $this->assertSame(Version::V1_1, Version::from('1.1'));
    }

    public function testTryFromInvalidReturnsNull(): void
    {
        $this->assertNull(Version::tryFrom('3.0'));
    }

    public function testV20Value(): void
    {
        $this->assertSame('2.0', Version::V2_0->value);
    }

    public function testV11Value(): void
    {
        $this->assertSame('1.1', Version::V1_1->value);
    }
}
