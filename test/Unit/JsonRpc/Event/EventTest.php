<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\Event;

use Horde\Rpc\JsonRpc\Event\BatchProcessing;
use Horde\Rpc\JsonRpc\Event\ErrorOccurred;
use Horde\Rpc\JsonRpc\Event\NotificationReceived;
use Horde\Rpc\JsonRpc\Event\RequestDispatched;
use Horde\Rpc\JsonRpc\Event\RequestReceived;
use Horde\Rpc\JsonRpc\Protocol\Batch;
use Horde\Rpc\JsonRpc\Protocol\Error;
use Horde\Rpc\JsonRpc\Protocol\ErrorCode;
use Horde\Rpc\JsonRpc\Protocol\Request;
use Horde\Rpc\JsonRpc\Protocol\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(RequestReceived::class)]
#[CoversClass(RequestDispatched::class)]
#[CoversClass(ErrorOccurred::class)]
#[CoversClass(NotificationReceived::class)]
#[CoversClass(BatchProcessing::class)]
class EventTest extends TestCase
{
    private function makeRequest(): Request
    {
        return new Request(Version::V2_0, 'test.method', [], 1);
    }

    public function testRequestReceived(): void
    {
        $request = $this->makeRequest();
        $event = new RequestReceived($request, Version::V2_0, 1234567890.123);
        $this->assertSame($request, $event->request);
        $this->assertSame(Version::V2_0, $event->version);
        $this->assertSame(1234567890.123, $event->receivedAt);
    }

    public function testRequestDispatched(): void
    {
        $request = $this->makeRequest();
        $event = new RequestDispatched($request, 42, 0.005);
        $this->assertSame($request, $event->request);
        $this->assertSame(42, $event->result);
        $this->assertSame(0.005, $event->duration);
    }

    public function testErrorOccurred(): void
    {
        $error = new Error(Version::V2_0, ErrorCode::InternalError, 'fail');
        $cause = new RuntimeException('root');
        $request = $this->makeRequest();
        $event = new ErrorOccurred($error, $cause, $request);
        $this->assertSame($error, $event->error);
        $this->assertSame($cause, $event->cause);
        $this->assertSame($request, $event->request);
    }

    public function testErrorOccurredWithoutCauseOrRequest(): void
    {
        $error = new Error(Version::V2_0, ErrorCode::ParseError, 'bad json');
        $event = new ErrorOccurred($error);
        $this->assertNull($event->cause);
        $this->assertNull($event->request);
    }

    public function testNotificationReceived(): void
    {
        $request = new Request(Version::V2_0, 'notify', [], null);
        $event = new NotificationReceived($request);
        $this->assertSame($request, $event->request);
    }

    public function testBatchProcessing(): void
    {
        $batch = new Batch([
            new Request(Version::V2_0, 'a', [], 1),
            new Request(Version::V2_0, 'b', [], null),
        ]);
        $event = new BatchProcessing($batch, 1, 1);
        $this->assertSame($batch, $event->batch);
        $this->assertSame(1, $event->requestCount);
        $this->assertSame(1, $event->notificationCount);
    }
}
