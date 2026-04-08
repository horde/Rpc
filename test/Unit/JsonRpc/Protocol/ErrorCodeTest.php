<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\Protocol;

use Horde\Rpc\JsonRpc\Protocol\ErrorCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErrorCode::class)]
class ErrorCodeTest extends TestCase
{
    public function testParseError(): void
    {
        $this->assertSame(-32700, ErrorCode::ParseError->value);
    }

    public function testInvalidRequest(): void
    {
        $this->assertSame(-32600, ErrorCode::InvalidRequest->value);
    }

    public function testMethodNotFound(): void
    {
        $this->assertSame(-32601, ErrorCode::MethodNotFound->value);
    }

    public function testInvalidParams(): void
    {
        $this->assertSame(-32602, ErrorCode::InvalidParams->value);
    }

    public function testInternalError(): void
    {
        $this->assertSame(-32603, ErrorCode::InternalError->value);
    }

    public function testFromIntValue(): void
    {
        $this->assertSame(ErrorCode::ParseError, ErrorCode::from(-32700));
    }

    public function testTryFromUnknownReturnsNull(): void
    {
        $this->assertNull(ErrorCode::tryFrom(-99999));
    }
}
