<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Test\Unit;

use Horde_ActiveSync;
use Horde_Controller_Request_Http;
use Horde_Exception;
use Horde_Rpc_ActiveSync;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(Horde_Rpc_ActiveSync::class)]
class ActiveSyncTest extends TestCase
{
    protected Horde_Rpc_ActiveSync $activeSyncRpc;

    /**
     * Tests if the errorHandler method of Horde_Rpc_ActiveSync will write passwords in the log.
     * To test this, you need to have 'zend.exception_ignore_args = Off' in the php.ini
     */
    #[RunInSeparateProcess]
    public function testNoPwInLogmessages(): void
    {
        $activeSync = $this->createStub(Horde_ActiveSync::class);
        $activeSync->method('getGetVars')->willReturn([
            'Cmd' => 'OPTIONS',
            'DeviceId' => 'test',
            'DeviceType' => 'test',
        ]);
        $activeSync->method('handleRequest')->willReturnCallback(function ($cmd, $device) {
            throw new Horde_Exception('test');
        });

        $request = $this->createStub(Horde_Controller_Request_Http::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getServerVars')->willReturn([
            'QUERY_STRING' => 'test',
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '',
        ]);
        $logger = new StrLogger();

        $this->activeSyncRpc = new Horde_Rpc_ActiveSync($request, [
            'server' => $activeSync,
            'logger' => $logger,
        ]);

        // Suppress exit() since the code calls exit after error handling
        $this->expectOutputString('');

        try {
            $this->activeSyncRpc->getResponse($request);
        } catch (Throwable $e) {
            // Catch any exception that might be thrown instead of exit
        }

        foreach ($logger->logs as $log) {
            $this->assertStringNotContainsString('password', $log['msg']);
        }
    }

    protected function pretendAuth(string $user, string $pw, Horde_Controller_Request_Http $request): void
    {
        $this->activeSyncRpc->getResponse($request);
    }
}
