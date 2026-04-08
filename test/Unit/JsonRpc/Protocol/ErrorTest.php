<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\Protocol;

use Horde\Rpc\JsonRpc\Protocol\Error;
use Horde\Rpc\JsonRpc\Protocol\ErrorCode;
use Horde\Rpc\JsonRpc\Protocol\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Error::class)]
class ErrorTest extends TestCase
{
    public function testConstructionWithEnumCode(): void
    {
        $error = new Error(
            Version::V2_0,
            ErrorCode::MethodNotFound,
            'Method not found',
        );
        $this->assertSame(Version::V2_0, $error->version);
        $this->assertSame(ErrorCode::MethodNotFound, $error->code);
        $this->assertSame('Method not found', $error->message);
        $this->assertNull($error->data);
        $this->assertNull($error->id);
    }

    public function testConstructionWithIntCode(): void
    {
        $error = new Error(Version::V2_0, -32050, 'Custom server error');
        $this->assertSame(-32050, $error->code);
    }

    public function testWithData(): void
    {
        $data = ['detail' => 'something went wrong'];
        $error = new Error(
            Version::V2_0,
            ErrorCode::InternalError,
            'Internal error',
            $data,
        );
        $this->assertSame($data, $error->data);
    }

    public function testWithId(): void
    {
        $error = new Error(
            Version::V1_1,
            ErrorCode::ParseError,
            'Parse error',
            null,
            42,
        );
        $this->assertSame(42, $error->id);
    }
}
