<?php

namespace Horde\Rpc\Test;

use Exception;
use Horde\Test\TestCase;
use Horde_ActiveSync;
use Horde_Controller_Request_Http;
use Horde_Exception;
use Horde_Rpc_ActiveSync;

class ActiveSyncTest extends TestCase
{
    protected Horde_Rpc_ActiveSync $activeSyncRpc;

    /**
     * Tests if the errorHandler method of Horde_Rpc_ActiveSync will write passwords in the log.
     * To test this, you need to have 'zend.exception_ignore_args = Off' in the php.ini
     */
    public function testNoPwInLogmessages()
    {
        $activeSync = $this->createMock(Horde_ActiveSync::class);
        $activeSync->method('getGetVars')->willReturn([
            'Cmd' => 'OPTIONS',
            'DeviceId' => 'test',
            'DeviceType' => 'test',
        ]);
        $activeSync->method('handleRequest')->willReturnCallback(function ($cmd, $device) {
            throw new Horde_Exception('test');
        });

        $request = $this->createMock(Horde_Controller_Request_Http::class);
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

        $this->pretendAuth('user', 'password', $request);

        foreach ($logger->logs as $log) {
            $this->assertFalse(strpos($log['msg'], 'password'));
        }
    }

    protected function pretendAuth($user, $pw, $request)
    {
        $this->activeSyncRpc->getResponse($request);
    }
}
