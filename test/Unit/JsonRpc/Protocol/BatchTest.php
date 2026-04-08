<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\Protocol;

use Horde\Rpc\JsonRpc\Protocol\Batch;
use Horde\Rpc\JsonRpc\Protocol\Error;
use Horde\Rpc\JsonRpc\Protocol\ErrorCode;
use Horde\Rpc\JsonRpc\Protocol\Request;
use Horde\Rpc\JsonRpc\Protocol\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Batch::class)]
class BatchTest extends TestCase
{
    public function testCount(): void
    {
        $batch = new Batch([
            new Request(Version::V2_0, 'a', [], 1),
            new Request(Version::V2_0, 'b', [], 2),
        ]);
        $this->assertCount(2, $batch);
    }

    public function testIteration(): void
    {
        $items = [
            new Request(Version::V2_0, 'a', [], 1),
            new Request(Version::V2_0, 'b', [], 2),
        ];
        $batch = new Batch($items);

        $collected = [];
        foreach ($batch as $item) {
            $collected[] = $item;
        }
        $this->assertSame($items, $collected);
    }

    public function testMixedRequestAndError(): void
    {
        $request = new Request(Version::V2_0, 'a', [], 1);
        $error = new Error(Version::V2_0, ErrorCode::InvalidRequest, 'bad', null, null);
        $batch = new Batch([$request, $error]);

        $this->assertCount(2, $batch);
        $items = iterator_to_array($batch);
        $this->assertInstanceOf(Request::class, $items[0]);
        $this->assertInstanceOf(Error::class, $items[1]);
    }

    public function testEmptyBatch(): void
    {
        $batch = new Batch([]);
        $this->assertCount(0, $batch);
    }
}
