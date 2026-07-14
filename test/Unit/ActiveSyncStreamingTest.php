<?php

declare(strict_types=1);

/**
 * Unit tests for the streaming Sync response path in Horde_Rpc_ActiveSync.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 */

namespace Horde\Rpc\Test\Unit;

use Horde_ActiveSync;
use Horde_Controller_Request_Http;
use Horde_Rpc_ActiveSync;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Horde_Rpc_ActiveSync::class)]
class ActiveSyncStreamingTest extends TestCase
{
    public function testStreamsOnlySyncPostWithStreamingEnabled(): void
    {
        $rpc = $this->rpc(['streaming' => true], 'Sync');
        $this->assertTrue($this->shouldStream($rpc, 'POST'));
    }

    public function testNoStreamingWhenDisabled(): void
    {
        $rpc = $this->rpc([], 'Sync');
        $this->assertFalse($this->shouldStream($rpc, 'POST'));
    }

    public function testNoStreamingForOtherCommands(): void
    {
        $rpc = $this->rpc(['streaming' => true], 'GetAttachment');
        $this->assertFalse($this->shouldStream($rpc, 'POST'));

        $rpc = $this->rpc(['streaming' => true], 'ItemOperations');
        $this->assertFalse($this->shouldStream($rpc, 'POST'));
    }

    public function testNoStreamingForNonPost(): void
    {
        $rpc = $this->rpc(['streaming' => true], 'Sync');
        $this->assertFalse($this->shouldStream($rpc, 'GET'));
    }

    public function testSendOutputReturnsEarlyWhenStreaming(): void
    {
        $rpc = $this->rpc(['streaming' => true], 'Sync');

        $ref = new ReflectionClass(Horde_Rpc_ActiveSync::class);
        $prop = $ref->getProperty('_streaming');
        $prop->setAccessible(true);
        $prop->setValue($rpc, true);

        // The buffered path would call ob_end_clean()/echo; streaming must
        // touch neither an output buffer nor the passed data.
        $this->expectOutputString('');
        $level = ob_get_level();
        $rpc->sendOutput('ignored');
        $this->assertSame($level, ob_get_level());

    protected function rpc(array $params, string $cmd): Horde_Rpc_ActiveSync
    {
        $activeSync = $this->createStub(Horde_ActiveSync::class);
        $activeSync->method('getGetVars')->willReturn([
            'Cmd' => $cmd,
            'DeviceId' => 'test',
            'DeviceType' => 'test',
        ]);

        $request = $this->createStub(Horde_Controller_Request_Http::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getServerVars')->willReturn([
            'QUERY_STRING' => 'Cmd=' . $cmd,
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/Microsoft-Server-ActiveSync',
        ]);

        return new Horde_Rpc_ActiveSync(
            $request,
            $params + ['server' => $activeSync]
        );
    }

    protected function shouldStream(Horde_Rpc_ActiveSync $rpc, string $method): bool
    {
        $ref = new ReflectionClass(Horde_Rpc_ActiveSync::class);
        $m = $ref->getMethod('_shouldStreamResponse');
        $m->setAccessible(true);

        return $m->invoke($rpc, ['REQUEST_METHOD' => $method]);
    }
}
