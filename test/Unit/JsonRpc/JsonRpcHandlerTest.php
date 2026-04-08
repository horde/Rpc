<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc;

use Horde\Http\ResponseFactory;
use Horde\Http\ServerRequest;
use Horde\Http\StreamFactory;
use Horde\Rpc\JsonRpc\JsonRpcHandler;
use Horde\Rpc\JsonRpc\Transport\HttpHandler;
use Horde\Rpc\JsonRpc\Transport\Middleware\JsonRpcMiddleware;
use Horde\Rpc\Test\Unit\JsonRpc\TestDouble\CallableMapProvider;
use Horde\Rpc\Test\Unit\JsonRpc\TestDouble\RecordingEventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonRpcHandler::class)]
class JsonRpcHandlerTest extends TestCase
{
    public function testGetHandlerReturnsHttpHandler(): void
    {
        $provider = new CallableMapProvider(['test' => fn() => 'ok']);
        $facade = new JsonRpcHandler(
            $provider,
            $provider,
            new ResponseFactory(),
            new StreamFactory(),
            new RecordingEventDispatcher(),
        );

        $this->assertInstanceOf(HttpHandler::class, $facade->getHandler());
    }

    public function testGetMiddlewareReturnsMiddleware(): void
    {
        $provider = new CallableMapProvider([]);
        $facade = new JsonRpcHandler(
            $provider,
            $provider,
            new ResponseFactory(),
            new StreamFactory(),
            new RecordingEventDispatcher(),
        );

        $this->assertInstanceOf(JsonRpcMiddleware::class, $facade->getMiddleware());
    }

    public function testFacadeHandlesRequest(): void
    {
        $provider = new CallableMapProvider(['echo' => fn(string $msg) => $msg]);
        $streamFactory = new StreamFactory();
        $facade = new JsonRpcHandler(
            $provider,
            $provider,
            new ResponseFactory(),
            $streamFactory,
            new RecordingEventDispatcher(),
        );

        $body = $streamFactory->createStream('{"jsonrpc":"2.0","method":"echo","params":["hello"],"id":1}');
        $request = new ServerRequest(
            method: 'POST',
            uri: '/rpc/jsonrpc',
            body: $body,
            headers: ['Content-Type' => 'application/json'],
        );

        $response = $facade->getHandler()->handle($request);
        $decoded = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('hello', $decoded['result']);
    }
}
