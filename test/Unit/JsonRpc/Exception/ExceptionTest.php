<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\Exception;

use Horde\Exception\HordeInvalidArgumentException;
use Horde\Exception\HordeRuntimeException;
use Horde\Exception\HordeThrowable;
use Horde\Rpc\JsonRpc\Exception\InternalErrorException;
use Horde\Rpc\JsonRpc\Exception\InvalidParamsException;
use Horde\Rpc\JsonRpc\Exception\InvalidRequestException;
use Horde\Rpc\JsonRpc\Exception\JsonRpcThrowable;
use Horde\Rpc\JsonRpc\Exception\MethodNotFoundException;
use Horde\Rpc\JsonRpc\Exception\ParseException;
use Horde\Rpc\JsonRpc\Exception\ServerErrorException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ParseException::class)]
#[CoversClass(InvalidRequestException::class)]
#[CoversClass(MethodNotFoundException::class)]
#[CoversClass(InvalidParamsException::class)]
#[CoversClass(InternalErrorException::class)]
#[CoversClass(ServerErrorException::class)]
class ExceptionTest extends TestCase
{
    public function testParseException(): void
    {
        $e = new ParseException();
        $this->assertInstanceOf(JsonRpcThrowable::class, $e);
        $this->assertInstanceOf(HordeThrowable::class, $e);
        $this->assertInstanceOf(HordeRuntimeException::class, $e);
        $this->assertSame(-32700, $e->getJsonRpcCode());
        $this->assertSame('Parse error', $e->getMessage());
        $this->assertNull($e->getErrorData());
    }

    public function testParseExceptionWithData(): void
    {
        $data = ['position' => 42];
        $e = new ParseException('Bad JSON at position 42', -32700, null, $data);
        $this->assertSame($data, $e->getErrorData());
    }

    public function testInvalidRequestException(): void
    {
        $e = new InvalidRequestException();
        $this->assertInstanceOf(JsonRpcThrowable::class, $e);
        $this->assertInstanceOf(HordeInvalidArgumentException::class, $e);
        $this->assertSame(-32600, $e->getJsonRpcCode());
        $this->assertSame('Invalid Request', $e->getMessage());
    }

    public function testMethodNotFoundException(): void
    {
        $e = new MethodNotFoundException('calendar.list');
        $this->assertInstanceOf(JsonRpcThrowable::class, $e);
        $this->assertInstanceOf(HordeRuntimeException::class, $e);
        $this->assertSame(-32601, $e->getJsonRpcCode());
        $this->assertSame('Method not found: calendar.list', $e->getMessage());
    }

    public function testInvalidParamsException(): void
    {
        $e = new InvalidParamsException();
        $this->assertInstanceOf(JsonRpcThrowable::class, $e);
        $this->assertInstanceOf(HordeInvalidArgumentException::class, $e);
        $this->assertSame(-32602, $e->getJsonRpcCode());
    }

    public function testInternalErrorException(): void
    {
        $e = new InternalErrorException();
        $this->assertInstanceOf(JsonRpcThrowable::class, $e);
        $this->assertSame(-32603, $e->getJsonRpcCode());
        $this->assertSame('Internal error', $e->getMessage());
    }

    public function testServerErrorException(): void
    {
        $e = new ServerErrorException('Custom error', -32050);
        $this->assertInstanceOf(JsonRpcThrowable::class, $e);
        $this->assertSame(-32050, $e->getJsonRpcCode());
        $this->assertSame('Custom error', $e->getMessage());
    }

    public function testServerErrorExceptionDefaultCode(): void
    {
        $e = new ServerErrorException();
        $this->assertSame(-32000, $e->getJsonRpcCode());
    }

    public function testServerErrorExceptionRejectsOutOfRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ServerErrorException('bad', -31000);
    }

    public function testServerErrorExceptionRejectsAboveRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ServerErrorException('bad', -33000);
    }

    public function testAllAreCatchableAsJsonRpcThrowable(): void
    {
        $exceptions = [
            new ParseException(),
            new InvalidRequestException(),
            new MethodNotFoundException('test'),
            new InvalidParamsException(),
            new InternalErrorException(),
            new ServerErrorException(),
        ];

        foreach ($exceptions as $e) {
            $this->assertInstanceOf(JsonRpcThrowable::class, $e);
        }
    }

    public function testPreviousExceptionChaining(): void
    {
        $previous = new RuntimeException('root cause');
        $e = new InternalErrorException('wrapper', -32603, $previous);
        $this->assertSame($previous, $e->getPrevious());
    }
}
