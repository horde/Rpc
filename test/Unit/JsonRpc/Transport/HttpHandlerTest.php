<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\Transport;

use Horde\Http\ResponseFactory;
use Horde\Http\ServerRequest;
use Horde\Http\StreamFactory;
use Horde\Http\Uri;
use Horde\Rpc\JsonRpc\Dispatch\Dispatcher;
use Horde\Rpc\JsonRpc\Event\BatchProcessing;
use Horde\Rpc\JsonRpc\Event\ErrorOccurred;
use Horde\Rpc\JsonRpc\Event\NotificationReceived;
use Horde\Rpc\JsonRpc\Event\RequestDispatched;
use Horde\Rpc\JsonRpc\Event\RequestReceived;
use Horde\Rpc\JsonRpc\Protocol\Codec;
use Horde\Rpc\JsonRpc\Transport\HttpHandler;
use Horde\Rpc\Test\Unit\JsonRpc\TestDouble\CallableMapProvider;
use Horde\Rpc\Test\Unit\JsonRpc\TestDouble\RecordingEventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

#[CoversClass(HttpHandler::class)]
class HttpHandlerTest extends TestCase
{
    private ResponseFactory $responseFactory;
    private StreamFactory $streamFactory;
    private RecordingEventDispatcher $events;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
        $this->streamFactory = new StreamFactory();
        $this->events = new RecordingEventDispatcher();
    }

    private function makeHandler(array $methods = [], int $maxBatch = 100): HttpHandler
    {
        $provider = new CallableMapProvider($methods);

        return new HttpHandler(
            new Codec(),
            new Dispatcher($provider, $provider),
            $this->responseFactory,
            $this->streamFactory,
            $this->events,
            $maxBatch,
        );
    }

    private function makeRequest(string $json): ServerRequest
    {
        $body = $this->streamFactory->createStream($json);

        return new ServerRequest(
            method: 'POST',
            uri: '/rpc/jsonrpc',
            body: $body,
            headers: ['Content-Type' => 'application/json'],
        );
    }

    private function decodeResponseBody(string $body): array
    {
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    // --- Single request tests ---

    public function testValidV20Request(): void
    {
        $handler = $this->makeHandler(['math.add' => fn(int $a, int $b) => $a + $b]);
        $request = $this->makeRequest('{"jsonrpc":"2.0","method":"math.add","params":[3,4],"id":1}');

        $response = $handler->handle($request);
        $body = $this->decodeResponseBody((string) $response->getBody());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('2.0', $body['jsonrpc']);
        $this->assertSame(7, $body['result']);
        $this->assertSame(1, $body['id']);
    }

    public function testValidV11Request(): void
    {
        $handler = $this->makeHandler(['test' => fn() => 'hello']);
        $request = $this->makeRequest('{"version":"1.1","method":"test","id":1}');

        $response = $handler->handle($request);
        $body = $this->decodeResponseBody((string) $response->getBody());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('1.1', $body['version']);
        $this->assertSame('hello', $body['result']);
    }

    public function testMethodNotFound(): void
    {
        $handler = $this->makeHandler([]);
        $request = $this->makeRequest('{"jsonrpc":"2.0","method":"nope","id":1}');

        $response = $handler->handle($request);
        $body = $this->decodeResponseBody((string) $response->getBody());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(-32601, $body['error']['code']);
    }

    public function testInvalidJson(): void
    {
        $handler = $this->makeHandler([]);
        $request = $this->makeRequest('{bad json');

        $response = $handler->handle($request);
        $body = $this->decodeResponseBody((string) $response->getBody());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(-32700, $body['error']['code']);
    }

    public function testV20Notification(): void
    {
        $called = false;
        $handler = $this->makeHandler(['notify' => function () use (&$called) {
            $called = true;

            return null;
        }]);
        $request = $this->makeRequest('{"jsonrpc":"2.0","method":"notify"}');

        $response = $handler->handle($request);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertTrue($called);
    }

    public function testGenericExceptionSanitized(): void
    {
        $handler = $this->makeHandler(['bad' => fn() => throw new RuntimeException('secret details')]);
        $request = $this->makeRequest('{"jsonrpc":"2.0","method":"bad","id":1}');

        $response = $handler->handle($request);
        $body = $this->decodeResponseBody((string) $response->getBody());

        $this->assertSame(-32603, $body['error']['code']);
        $this->assertSame('Internal error', $body['error']['message']);
        $this->assertStringNotContainsString('secret', (string) $response->getBody());
    }

    // --- Batch tests ---

    public function testBatchTwoRequests(): void
    {
        $handler = $this->makeHandler([
            'a' => fn() => 1,
            'b' => fn() => 2,
        ]);
        $json = '[{"jsonrpc":"2.0","method":"a","id":1},{"jsonrpc":"2.0","method":"b","id":2}]';
        $request = $this->makeRequest($json);

        $response = $handler->handle($request);
        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $body);
        $this->assertSame(1, $body[0]['result']);
        $this->assertSame(2, $body[1]['result']);
    }

    public function testBatchWithNotification(): void
    {
        $handler = $this->makeHandler([
            'a' => fn() => 1,
            'b' => fn() => 2,
        ]);
        $json = '[{"jsonrpc":"2.0","method":"a","id":1},{"jsonrpc":"2.0","method":"b"}]';
        $request = $this->makeRequest($json);

        $response = $handler->handle($request);
        $body = json_decode((string) $response->getBody(), true);

        // Only one response (notification excluded)
        $this->assertCount(1, $body);
        $this->assertSame(1, $body[0]['result']);
    }

    public function testBatchAllNotifications(): void
    {
        $handler = $this->makeHandler(['a' => fn() => 1]);
        $json = '[{"jsonrpc":"2.0","method":"a"}]';
        $request = $this->makeRequest($json);

        $response = $handler->handle($request);

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testBatchExceedsMaxSize(): void
    {
        $handler = $this->makeHandler(['a' => fn() => 1], maxBatch: 2);
        $json = '[{"jsonrpc":"2.0","method":"a","id":1},{"jsonrpc":"2.0","method":"a","id":2},{"jsonrpc":"2.0","method":"a","id":3}]';
        $request = $this->makeRequest($json);

        $response = $handler->handle($request);
        $body = $this->decodeResponseBody((string) $response->getBody());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(-32600, $body['error']['code']);
    }

    public function testEmptyBatchArray(): void
    {
        $handler = $this->makeHandler([]);
        $request = $this->makeRequest('[]');

        $response = $handler->handle($request);
        $body = $this->decodeResponseBody((string) $response->getBody());

        $this->assertSame(-32600, $body['error']['code']);
    }

    // --- Event emission tests ---

    public function testRequestReceivedEvent(): void
    {
        $handler = $this->makeHandler(['test' => fn() => 1]);
        $request = $this->makeRequest('{"jsonrpc":"2.0","method":"test","id":1}');

        $handler->handle($request);

        $events = $this->events->getEventsOfType(RequestReceived::class);
        $this->assertCount(1, $events);
        $this->assertSame('test', $events[0]->request->method);
    }

    public function testRequestDispatchedEvent(): void
    {
        $handler = $this->makeHandler(['test' => fn() => 42]);
        $request = $this->makeRequest('{"jsonrpc":"2.0","method":"test","id":1}');

        $handler->handle($request);

        $events = $this->events->getEventsOfType(RequestDispatched::class);
        $this->assertCount(1, $events);
        $this->assertSame(42, $events[0]->result);
        $this->assertGreaterThan(0.0, $events[0]->duration);
    }

    public function testNotificationReceivedEvent(): void
    {
        $handler = $this->makeHandler(['test' => fn() => null]);
        $request = $this->makeRequest('{"jsonrpc":"2.0","method":"test"}');

        $handler->handle($request);

        $events = $this->events->getEventsOfType(NotificationReceived::class);
        $this->assertCount(1, $events);
    }

    public function testErrorOccurredEvent(): void
    {
        $handler = $this->makeHandler([]);
        $request = $this->makeRequest('{"jsonrpc":"2.0","method":"nope","id":1}');

        $handler->handle($request);

        $events = $this->events->getEventsOfType(ErrorOccurred::class);
        $this->assertCount(1, $events);
        $code = $events[0]->error->code;
        $codeValue = $code instanceof \Horde\Rpc\JsonRpc\Protocol\ErrorCode ? $code->value : $code;
        $this->assertSame(-32601, $codeValue);
    }

    public function testBatchProcessingEvent(): void
    {
        $handler = $this->makeHandler(['a' => fn() => 1]);
        $json = '[{"jsonrpc":"2.0","method":"a","id":1},{"jsonrpc":"2.0","method":"a"}]';
        $request = $this->makeRequest($json);

        $handler->handle($request);

        $events = $this->events->getEventsOfType(BatchProcessing::class);
        $this->assertCount(1, $events);
        $this->assertSame(1, $events[0]->requestCount);
        $this->assertSame(1, $events[0]->notificationCount);
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

    private function makeMiddlewareRequest(
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

    public function testProcessMatchingPostDelegatesToHandler(): void
    {
        $handler = $this->makeHandler(['test' => fn() => 'ok']);
        $body = $this->streamFactory->createStream('{"jsonrpc":"2.0","method":"test","id":1}');
        $request = new ServerRequest(
            method: 'POST',
            uri: new Uri('/rpc/jsonrpc'),
            body: $body,
            headers: ['Content-Type' => 'application/json'],
        );

        $response = $handler->process($request, $this->makeNextHandler());

        $this->assertSame(200, $response->getStatusCode());
        $decoded = $this->decodeResponseBody((string) $response->getBody());
        $this->assertSame('ok', $decoded['result']);
    }

    public function testProcessGetPassesToNext(): void
    {
        $handler = $this->makeHandler();
        $request = $this->makeMiddlewareRequest('GET', '/rpc/jsonrpc');

        $response = $handler->process($request, $this->makeNextHandler());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testProcessWrongPathPassesToNext(): void
    {
        $handler = $this->makeHandler();
        $request = $this->makeMiddlewareRequest('POST', '/other/path');

        $response = $handler->process($request, $this->makeNextHandler());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testProcessWrongContentTypePassesToNext(): void
    {
        $handler = $this->makeHandler();
        $request = $this->makeMiddlewareRequest('POST', '/rpc/jsonrpc', 'text/xml');

        $response = $handler->process($request, $this->makeNextHandler());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testProcessJsonRpcContentTypeAccepted(): void
    {
        $handler = $this->makeHandler(['test' => fn() => 'ok']);
        $body = $this->streamFactory->createStream('{"jsonrpc":"2.0","method":"test","id":1}');
        $request = new ServerRequest(
            method: 'POST',
            uri: new Uri('/rpc/jsonrpc'),
            body: $body,
            headers: ['Content-Type' => 'application/json-rpc'],
        );

        $response = $handler->process($request, $this->makeNextHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testProcessCustomPath(): void
    {
        $provider = new CallableMapProvider(['test' => fn() => 'ok']);
        $handler = new HttpHandler(
            new Codec(),
            new Dispatcher($provider, $provider),
            $this->responseFactory,
            $this->streamFactory,
            $this->events,
            path: '/api/v2/rpc',
        );
        $body = $this->streamFactory->createStream('{"jsonrpc":"2.0","method":"test","id":1}');
        $request = new ServerRequest(
            method: 'POST',
            uri: new Uri('/api/v2/rpc'),
            body: $body,
            headers: ['Content-Type' => 'application/json'],
        );

        $response = $handler->process($request, $this->makeNextHandler());

        $this->assertSame(200, $response->getStatusCode());
    }
}
