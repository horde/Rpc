<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\Dispatch;

use Horde\Rpc\Dispatch\ApiCallContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiCallContext::class)]
class ApiCallContextTest extends TestCase
{
    public function testEmptyByDefault(): void
    {
        $ctx = new ApiCallContext();
        $this->assertSame([], $ctx->getAttributes());
    }

    public function testConstructWithAttributes(): void
    {
        $ctx = new ApiCallContext(['protocol' => 'jsonrpc', 'version' => '2.0']);
        $this->assertSame('jsonrpc', $ctx->getAttribute('protocol'));
        $this->assertSame('2.0', $ctx->getAttribute('version'));
    }

    public function testGetAttributeDefault(): void
    {
        $ctx = new ApiCallContext();
        $this->assertNull($ctx->getAttribute('missing'));
        $this->assertSame('fallback', $ctx->getAttribute('missing', 'fallback'));
    }

    public function testWithAttributeReturnsNewInstance(): void
    {
        $ctx = new ApiCallContext(['a' => 1]);
        $ctx2 = $ctx->withAttribute('b', 2);

        $this->assertNotSame($ctx, $ctx2);
        $this->assertNull($ctx->getAttribute('b'));
        $this->assertSame(2, $ctx2->getAttribute('b'));
        $this->assertSame(1, $ctx2->getAttribute('a'));
    }

    public function testWithAttributeOverwritesExisting(): void
    {
        $ctx = new ApiCallContext(['key' => 'old']);
        $ctx2 = $ctx->withAttribute('key', 'new');

        $this->assertSame('old', $ctx->getAttribute('key'));
        $this->assertSame('new', $ctx2->getAttribute('key'));
    }

    public function testGetAttributesReturnsAll(): void
    {
        $attrs = ['protocol' => 'mcp', 'user' => 'admin'];
        $ctx = new ApiCallContext($attrs);
        $this->assertSame($attrs, $ctx->getAttributes());
    }

    public function testChainedWithAttributes(): void
    {
        $ctx = (new ApiCallContext())
            ->withAttribute('protocol', 'soap')
            ->withAttribute('user', 'test')
            ->withAttribute('authenticated', true);

        $this->assertSame('soap', $ctx->getAttribute('protocol'));
        $this->assertSame('test', $ctx->getAttribute('user'));
        $this->assertTrue($ctx->getAttribute('authenticated'));
        $this->assertCount(3, $ctx->getAttributes());
    }
}
