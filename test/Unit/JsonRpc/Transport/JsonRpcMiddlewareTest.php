<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\Transport;

use Horde\Http\ResponseFactory;
use Horde\Http\ServerRequest;
use Horde\Http\StreamFactory;
use Horde\Http\Uri;
use Horde\Rpc\JsonRpc\Transport\Middleware\JsonRpcMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(JsonRpcMiddleware::class)]
class JsonRpcMiddlewareTest extends TestCase
{
    private StreamFactory $streamFactory;

    protected function setUp(): void
    {
        $this->streamFactory = new StreamFactory();
    }

    private function makeMockHandler(): RequestHandlerInterface
    {
        $responseFactory = new ResponseFactory();
        $streamFactory = $this->streamFactory;

        return new class ($responseFactory, $streamFactory) implements RequestHandlerInterface {
            public function __construct(
                private readonly \Psr\Http\Message\ResponseFactoryInterface $rf,
                private readonly \Psr\Http\Message\StreamFactoryInterface $sf,
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $body = $this->sf->createStream('{"jsonrpc":"2.0","result":"handled","id":1}');

                return $this->rf->createResponse(200)
                    ->withBody($body)
                    ->withHeader('Content-Type', 'application/json');
            }
        };
    }

    private function makeNextHandler(): RequestHandlerInterface
    {
        $responseFactory = new ResponseFactory();

        return new class ($responseFactory) implements RequestHandlerInterface {
            public function __construct(
                private readonly \Psr\Http\Message\ResponseFactoryInterface $rf,
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->rf->createResponse(404);
            }
        };
    }

    private function makeServerRequest(
        string $method,
        string $path,
        string $contentType = 'application/json',
    ): ServerRequest {
        return new ServerRequest(
            method: $method,
            uri: new Uri($path),
            headers: ['Content-Type' => $contentType],
        );
    }

    public function testPostJsonMatchingPathDelegatesToHandler(): void
    {
        $middleware = new JsonRpcMiddleware($this->makeMockHandler());
        $request = $this->makeServerRequest('POST', '/rpc/jsonrpc');

        $response = $middleware->process($request, $this->makeNextHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('handled', (string) $response->getBody());
    }

    public function testGetPassesToNext(): void
    {
        $middleware = new JsonRpcMiddleware($this->makeMockHandler());
        $request = $this->makeServerRequest('GET', '/rpc/jsonrpc');

        $response = $middleware->process($request, $this->makeNextHandler());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testWrongPathPassesToNext(): void
    {
        $middleware = new JsonRpcMiddleware($this->makeMockHandler());
        $request = $this->makeServerRequest('POST', '/other/path');

        $response = $middleware->process($request, $this->makeNextHandler());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testWrongContentTypePassesToNext(): void
    {
        $middleware = new JsonRpcMiddleware($this->makeMockHandler());
        $request = $this->makeServerRequest('POST', '/rpc/jsonrpc', 'text/xml');

        $response = $middleware->process($request, $this->makeNextHandler());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testJsonRpcContentTypeAccepted(): void
    {
        $middleware = new JsonRpcMiddleware($this->makeMockHandler());
        $request = $this->makeServerRequest('POST', '/rpc/jsonrpc', 'application/json-rpc');

        $response = $middleware->process($request, $this->makeNextHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCustomPath(): void
    {
        $middleware = new JsonRpcMiddleware($this->makeMockHandler(), '/api/v2/rpc');
        $request = $this->makeServerRequest('POST', '/api/v2/rpc');

        $response = $middleware->process($request, $this->makeNextHandler());

        $this->assertSame(200, $response->getStatusCode());
    }
}
