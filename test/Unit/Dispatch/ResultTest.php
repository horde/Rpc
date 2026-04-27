<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\Dispatch;

use Horde\Rpc\Dispatch\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Result::class)]
class ResultTest extends TestCase
{
    public function testValueAccess(): void
    {
        $result = new Result(42);
        $this->assertSame(42, $result->value);
    }

    public function testNullValue(): void
    {
        $result = new Result(null);
        $this->assertNull($result->value);
    }

    public function testArrayValue(): void
    {
        $data = ['foo' => 'bar'];
        $result = new Result($data);
        $this->assertSame($data, $result->value);
    }
}
