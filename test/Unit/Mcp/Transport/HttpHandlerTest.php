<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\Mcp\Transport;

use Horde\Http\ResponseFactory;
use Horde\Http\ServerRequest;
use Horde\Http\StreamFactory;
use Horde\Http\Uri;
use Horde\Rpc\JsonRpc\Dispatch\CallableMapProvider;
use Horde\Rpc\JsonRpc\Dispatch\MethodDescriptor;
use Horde\Rpc\Mcp\McpRouter;
use Horde\Rpc\Mcp\Protocol\ServerCapabilities;
use Horde\Rpc\Mcp\Protocol\ServerInfo;
use Horde\Rpc\Mcp\Transport\HttpHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(HttpHandler::class)]
class HttpHandlerTest extends TestCase
{
    private ResponseFactory $responseFactory;
    private StreamFactory $streamFactory;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
        $this->streamFactory = new StreamFactory();
    }

    private function makeHandler(array $methods = [], array $descriptors = []): HttpHandler
    {
        $provider = new CallableMapProvider($methods, $descriptors);
        $router = new McpRouter(
            new ServerInfo('test', '1.0.0'),
            new ServerCapabilities(tools: true),
            $provider,
            $provider,
        );

        return new HttpHandler($router, $this->responseFactory, $this->streamFactory);
    }

    private function makeRequest(string $json, array $attributes = []): ServerRequest
    {
        $body = $this->streamFactory->createStream($json);
        $request = new ServerRequest(
            method: 'POST',
            uri: '/mcp',
            body: $body,
            headers: ['Content-Type' => 'application/json'],
        );
        foreach ($attributes as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $request;
    }

    private function decodeBody(string $body): array
    {
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    // --- initialize ---

    public function testInitialize(): void
    {
        $handler = $this->makeHandler();
        $request = $this->makeRequest('{"jsonrpc":"2.0","method":"initialize","params":{"protocolVersion":"2025-11-25"},"id":1}');

        $response = $handler->handle($request);
        $body = $this->decodeBody((string) $response->getBody());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('2.0', $body['jsonrpc']);
        $this->assertSame(1, $body['id']);
        $this->assertSame('2025-11-25', $body['result']['protocolVersion']);
        $this->assertSame('test', $body['result']['serverInfo']['name']);
    }

    // --- notifications return 202 ---

    public function testNotificationReturns202(): void
    {
        $handler = $this->makeHandler();
        $request = $this->makeRequest('{"jsonrpc":"2.0","method":"notifications/initialized"}');

        $response = $handler->handle($request);

        $this->assertSame(202, $response->getStatusCode());
    }

    // --- tools/list ---

    public function testToolsList(): void
    {
        $handler = $this->makeHandler([
            'math.add' => fn(float $a, float $b) => $a + $b,
        ]);
        $request = $this->makeRequest('{"jsonrpc":"2.0","method":"tools/list","id":2}');

        $response = $handler->handle($request);
        $body = $this->decodeBody((string) $response->getBody());

        $this->assertCount(1, $body['result']['tools']);
        $this->assertSame('math.add', $body['result']['tools'][0]['name']);
    }

    // --- tools/call ---

    public function testToolsCall(): void
    {
        $handler = $this->makeHandler([
            'math.add' => fn(float $a, float $b) => $a + $b,
        ]);
        $request = $this->makeRequest(
            '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"math.add","arguments":[3,4]},"id":3}'
        );

        $response = $handler->handle($request);
        $body = $this->decodeBody((string) $response->getBody());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($body['result']['isError']);
        $this->assertSame('7', $body['result']['content'][0]['text']);
    }

    // --- auth ---

    public function testToolsCallUnauthorized(): void
    {
        $handler = $this->makeHandler(
            ['secret' => fn() => 'data'],
            ['secret' => new MethodDescriptor('secret', permissions: ['admin'])],
        );
        $request = $this->makeRequest(
            '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"secret","arguments":[]},"id":4}'
        );

        $response = $handler->handle($request);
        $body = $this->decodeBody((string) $response->getBody());

        $this->assertSame(-32603, $body['error']['code']);
        $this->assertStringContainsString('Unauthorized', $body['error']['message']);
    }

    public function testToolsCallAuthorizedViaAttributes(): void
    {
        $handler = $this->makeHandler(
            ['secret' => fn() => 'data'],
            ['secret' => new MethodDescriptor('secret', permissions: ['admin'])],
        );
        $request = $this->makeRequest(
            '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"secret","arguments":[]},"id":5}',
            ['auth_permissions' => ['admin'], 'authenticated' => true],
        );

        $response = $handler->handle($request);
        $body = $this->decodeBody((string) $response->getBody());

        $this->assertFalse($body['result']['isError']);
    }

    // --- error handling ---

    public function testInvalidJson(): void
    {
        $handler = $this->makeHandler();
        $request = $this->makeRequest('{bad json');

        $response = $handler->handle($request);
        $body = $this->decodeBody((string) $response->getBody());

        $this->assertSame(-32700, $body['error']['code']);
    }

    public function testUnknownMethod(): void
    {
        $handler = $this->makeHandler();
        $request = $this->makeRequest('{"jsonrpc":"2.0","method":"nope","id":6}');

        $response = $handler->handle($request);
        $body = $this->decodeBody((string) $response->getBody());

        $this->assertSame(-32601, $body['error']['code']);
    }

    // --- ping ---

    public function testPing(): void
    {
        $handler = $this->makeHandler();
        $request = $this->makeRequest('{"jsonrpc":"2.0","method":"ping","id":7}');

        $response = $handler->handle($request);
        $body = $this->decodeBody((string) $response->getBody());

        $this->assertSame([], $body['result']);
    }

    // --- Middleware (process()) tests ---

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

    public function testProcessMatchingPostDelegatesToHandler(): void
    {
        $handler = $this->makeHandler();
        $body = $this->streamFactory->createStream('{"jsonrpc":"2.0","method":"ping","id":1}');
        $request = new ServerRequest(
            method: 'POST',
            uri: new Uri('/mcp'),
            body: $body,
            headers: ['Content-Type' => 'application/json'],
        );

        $response = $handler->process($request, $this->makeNextHandler());

        $this->assertSame(200, $response->getStatusCode());
        $decoded = $this->decodeBody((string) $response->getBody());
        $this->assertSame([], $decoded['result']);
    }

    public function testProcessGetPassesToNext(): void
    {
        $handler = $this->makeHandler();
        $request = new ServerRequest(
            method: 'GET',
            uri: new Uri('/mcp'),
            headers: ['Content-Type' => 'application/json'],
        );

        $response = $handler->process($request, $this->makeNextHandler());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testProcessWrongPathPassesToNext(): void
    {
        $handler = $this->makeHandler();
        $request = new ServerRequest(
            method: 'POST',
            uri: new Uri('/other/path'),
            headers: ['Content-Type' => 'application/json'],
        );

        $response = $handler->process($request, $this->makeNextHandler());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testProcessWrongContentTypePassesToNext(): void
    {
        $handler = $this->makeHandler();
        $request = new ServerRequest(
            method: 'POST',
            uri: new Uri('/mcp'),
            headers: ['Content-Type' => 'text/xml'],
        );

        $response = $handler->process($request, $this->makeNextHandler());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testProcessCustomPath(): void
    {
        $provider = new CallableMapProvider([]);
        $router = new McpRouter(
            new ServerInfo('test', '1.0.0'),
            new ServerCapabilities(tools: true),
            $provider,
            $provider,
        );
        $handler = new HttpHandler($router, $this->responseFactory, $this->streamFactory, '/custom/mcp');
        $body = $this->streamFactory->createStream('{"jsonrpc":"2.0","method":"ping","id":1}');
        $request = new ServerRequest(
            method: 'POST',
            uri: new Uri('/custom/mcp'),
            body: $body,
            headers: ['Content-Type' => 'application/json'],
        );

        $response = $handler->process($request, $this->makeNextHandler());

        $this->assertSame(200, $response->getStatusCode());
    }
}
