<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\Mcp;

use Horde\Rpc\Dispatch\CallableMapProvider;
use Horde\Rpc\Dispatch\MethodDescriptor;
use Horde\Rpc\Mcp\AuthContext;
use Horde\Rpc\Mcp\McpError;
use Horde\Rpc\Mcp\McpRouter;
use Horde\Rpc\Mcp\Protocol\ResourceContent;
use Horde\Rpc\Mcp\Protocol\ResourceDescriptor;
use Horde\Rpc\Mcp\Protocol\ServerCapabilities;
use Horde\Rpc\Mcp\Protocol\ServerInfo;
use Horde\Rpc\Mcp\ResourceProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(McpRouter::class)]
class McpRouterTest extends TestCase
{
    private function makeRouter(
        array $methods = [],
        array $descriptors = [],
        ?ResourceProviderInterface $resourceProvider = null,
    ): McpRouter {
        $provider = new CallableMapProvider($methods, $descriptors);

        return new McpRouter(
            new ServerInfo('test-server', '1.0.0', 'Test'),
            new ServerCapabilities(
                tools: true,
                resources: $resourceProvider !== null,
            ),
            $provider,
            $provider,
            $resourceProvider,
        );
    }

    // --- initialize ---

    public function testInitialize(): void
    {
        $router = $this->makeRouter();
        $result = $router->route('initialize', ['protocolVersion' => '2025-11-25'], AuthContext::anonymous());

        $this->assertSame('2025-11-25', $result['protocolVersion']);
        $this->assertSame('test-server', $result['serverInfo']['name']);
        $this->assertSame('1.0.0', $result['serverInfo']['version']);
        $this->assertArrayHasKey('capabilities', $result);
    }

    // --- ping ---

    public function testPing(): void
    {
        $router = $this->makeRouter();
        $result = $router->route('ping', [], AuthContext::anonymous());

        $this->assertSame([], $result);
    }

    // --- tools/list ---

    public function testToolsList(): void
    {
        $router = $this->makeRouter([
            'math.add' => fn(float $a, float $b) => $a + $b,
        ]);

        $result = $router->route('tools/list', [], AuthContext::anonymous());

        $this->assertCount(1, $result['tools']);
        $this->assertSame('math.add', $result['tools'][0]['name']);
        $this->assertArrayHasKey('inputSchema', $result['tools'][0]);
    }

    // --- tools/call ---

    public function testToolsCall(): void
    {
        $router = $this->makeRouter([
            'math.add' => fn(float $a, float $b) => $a + $b,
        ]);

        $result = $router->route('tools/call', [
            'name' => 'math.add',
            'arguments' => [3.0, 4.0],
        ], AuthContext::anonymous());

        $this->assertFalse($result['isError']);
        $this->assertSame('7', $result['content'][0]['text']);
        $this->assertSame('text', $result['content'][0]['type']);
    }

    public function testToolsCallUnknownTool(): void
    {
        $router = $this->makeRouter([]);

        $this->expectException(McpError::class);
        $this->expectExceptionMessage('Unknown tool: nope');
        $router->route('tools/call', ['name' => 'nope'], AuthContext::anonymous());
    }

    public function testToolsCallExceptionBecomesErrorResult(): void
    {
        $router = $this->makeRouter([
            'bad' => fn() => throw new RuntimeException('boom'),
        ]);

        $result = $router->route('tools/call', [
            'name' => 'bad',
            'arguments' => [],
        ], AuthContext::anonymous());

        $this->assertTrue($result['isError']);
        $this->assertSame('boom', $result['content'][0]['text']);
    }

    // --- auth ---

    public function testToolsCallPermissionDenied(): void
    {
        $router = $this->makeRouter(
            ['admin.reset' => fn() => 'done'],
            ['admin.reset' => new MethodDescriptor('admin.reset', permissions: ['horde:admin'])],
        );

        $this->expectException(McpError::class);
        $this->expectExceptionMessage('Unauthorized');
        $router->route('tools/call', [
            'name' => 'admin.reset',
            'arguments' => [],
        ], AuthContext::anonymous());
    }

    public function testToolsCallPermissionGranted(): void
    {
        $router = $this->makeRouter(
            ['admin.reset' => fn() => 'done'],
            ['admin.reset' => new MethodDescriptor('admin.reset', permissions: ['horde:admin'])],
        );

        $result = $router->route('tools/call', [
            'name' => 'admin.reset',
            'arguments' => [],
        ], AuthContext::withPermissions(['horde:admin']));

        $this->assertFalse($result['isError']);
    }

    public function testToolsCallPublicMethodNoAuthRequired(): void
    {
        $router = $this->makeRouter([
            'ping' => fn() => 'pong',
        ]);

        $result = $router->route('tools/call', [
            'name' => 'ping',
            'arguments' => [],
        ], AuthContext::anonymous());

        $this->assertFalse($result['isError']);
    }

    // --- notifications ---

    public function testIsNotification(): void
    {
        $router = $this->makeRouter();

        $this->assertTrue($router->isNotification('notifications/initialized'));
        $this->assertTrue($router->isNotification('notifications/tools/list_changed'));
        $this->assertFalse($router->isNotification('initialize'));
        $this->assertFalse($router->isNotification('tools/call'));
    }

    // --- resources ---

    public function testResourcesList(): void
    {
        $rp = $this->createStub(ResourceProviderInterface::class);
        $rp->method('listResources')->willReturn([
            new ResourceDescriptor('file:///readme.md', 'README', 'Project readme', 'text/markdown'),
        ]);

        $router = $this->makeRouter([], [], $rp);
        $result = $router->route('resources/list', [], AuthContext::anonymous());

        $this->assertCount(1, $result['resources']);
        $this->assertSame('file:///readme.md', $result['resources'][0]['uri']);
    }

    public function testResourcesRead(): void
    {
        $rp = $this->createStub(ResourceProviderInterface::class);
        $rp->method('hasResource')->willReturn(true);
        $rp->method('readResource')->willReturn(
            new ResourceContent('file:///readme.md', 'Hello', mimeType: 'text/markdown'),
        );

        $router = $this->makeRouter([], [], $rp);
        $result = $router->route('resources/read', ['uri' => 'file:///readme.md'], AuthContext::anonymous());

        $this->assertSame('Hello', $result['contents'][0]['text']);
    }

    public function testResourcesReadNotFound(): void
    {
        $rp = $this->createStub(ResourceProviderInterface::class);
        $rp->method('hasResource')->willReturn(false);

        $router = $this->makeRouter([], [], $rp);

        $this->expectException(McpError::class);
        $this->expectExceptionMessage('Resource not found');
        $router->route('resources/read', ['uri' => 'file:///nope'], AuthContext::anonymous());
    }

    public function testResourcesListWithoutProvider(): void
    {
        $router = $this->makeRouter([]);

        $this->expectException(McpError::class);
        $router->route('resources/list', [], AuthContext::anonymous());
    }

    // --- unknown method ---

    public function testUnknownMethod(): void
    {
        $router = $this->makeRouter([]);

        $this->expectException(McpError::class);
        $this->expectExceptionMessage('Method not found');
        $router->route('unknown/method', [], AuthContext::anonymous());
    }
}
