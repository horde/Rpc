<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\Protocol;

use Horde\Rpc\JsonRpc\Protocol\Request;
use Horde\Rpc\JsonRpc\Protocol\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Request::class)]
class RequestTest extends TestCase
{
    public function testConstruction(): void
    {
        $request = new Request(Version::V2_0, 'math.add', [1, 2], 1);
        $this->assertSame(Version::V2_0, $request->version);
        $this->assertSame('math.add', $request->method);
        $this->assertSame([1, 2], $request->params);
        $this->assertSame(1, $request->id);
    }

    public function testIsNotificationWithNullId(): void
    {
        $request = new Request(Version::V2_0, 'notify', [], null);
        $this->assertTrue($request->isNotification());
    }

    public function testIsNotNotificationWithIntId(): void
    {
        $request = new Request(Version::V2_0, 'call', [], 0);
        $this->assertFalse($request->isNotification());
    }

    public function testIsNotNotificationWithStringId(): void
    {
        $request = new Request(Version::V2_0, 'call', [], 'abc');
        $this->assertFalse($request->isNotification());
    }

    public function testIsNotNotificationWithEmptyStringId(): void
    {
        $request = new Request(Version::V2_0, 'call', [], '');
        $this->assertFalse($request->isNotification());
    }

    public function testNamedParams(): void
    {
        $params = ['name' => 'John', 'age' => 30];
        $request = new Request(Version::V2_0, 'user.create', $params, 1);
        $this->assertSame($params, $request->params);
    }
}
