<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\Protocol;

use Horde\Rpc\JsonRpc\Protocol\Response;
use Horde\Rpc\JsonRpc\Protocol\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Response::class)]
class ResponseTest extends TestCase
{
    public function testConstruction(): void
    {
        $response = new Response(Version::V2_0, 42, 1);
        $this->assertSame(Version::V2_0, $response->version);
        $this->assertSame(42, $response->result);
        $this->assertSame(1, $response->id);
    }

    public function testNullResult(): void
    {
        $response = new Response(Version::V2_0, null, 'abc');
        $this->assertNull($response->result);
    }

    public function testArrayResult(): void
    {
        $data = ['foo' => 'bar'];
        $response = new Response(Version::V1_1, $data, 1);
        $this->assertSame($data, $response->result);
    }

    public function testStringId(): void
    {
        $response = new Response(Version::V2_0, true, 'request-1');
        $this->assertSame('request-1', $response->id);
    }
}
