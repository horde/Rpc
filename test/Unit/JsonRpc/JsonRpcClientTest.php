<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc;

use Horde\Http\Response;
use Horde\Http\StreamFactory;
use Horde\Rpc\JsonRpc\JsonRpcClient;
use Horde\Rpc\JsonRpc\Protocol\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(JsonRpcClient::class)]
class JsonRpcClientTest extends TestCase
{
    private StreamFactory $streamFactory;

    protected function setUp(): void
    {
        $this->streamFactory = new StreamFactory();
    }

    private function makeMockHttpClient(string $responseBody, int $statusCode = 200): ClientInterface
    {
        $sf = $this->streamFactory;

        return new class ($responseBody, $statusCode, $sf) implements ClientInterface {
            public ?RequestInterface $lastRequest = null;

            public function __construct(
                private readonly string $responseBody,
                private readonly int $statusCode,
                private readonly StreamFactory $sf,
            ) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->lastRequest = $request;

                return new Response($this->statusCode, body: $this->sf->createStream($this->responseBody));
            }
        };
    }

    public function testCallReturnsResult(): void
    {
        $http = $this->makeMockHttpClient('{"jsonrpc":"2.0","result":42,"id":1}');
        $client = new JsonRpcClient('http://example.com/rpc', $http);

        $result = $client->call('math.add', [3, 4]);

        $this->assertSame(42, $result);
    }

    public function testCallSendsToConfiguredUrl(): void
    {
        $http = $this->makeMockHttpClient('{"jsonrpc":"2.0","result":null,"id":1}');
        $client = new JsonRpcClient('http://my-server.local/jsonrpc', $http);

        $client->call('test');

        $this->assertSame('http://my-server.local/jsonrpc', (string) $http->lastRequest->getUri());
    }

    public function testNotifySendsNoId(): void
    {
        $http = $this->makeMockHttpClient('', 204);
        $client = new JsonRpcClient('http://example.com/rpc', $http);

        $client->notify('log.event', ['data']);

        $body = json_decode((string) $http->lastRequest->getBody(), true);
        $this->assertSame('log.event', $body['method']);
        $this->assertArrayNotHasKey('id', $body);
    }

    public function testBatchReturnsResponses(): void
    {
        $http = $this->makeMockHttpClient(
            '[{"jsonrpc":"2.0","result":1,"id":1},{"jsonrpc":"2.0","result":2,"id":2}]'
        );
        $client = new JsonRpcClient('http://example.com/rpc', $http);

        $results = $client->batch([
            ['method' => 'a'],
            ['method' => 'b'],
        ]);

        $this->assertCount(2, $results);
    }

    public function testDefaultCurlClientConstruction(): void
    {
        // Construction with just a URL should succeed (uses Curl default)
        $client = new JsonRpcClient('http://example.com/rpc');
        $this->assertInstanceOf(JsonRpcClient::class, $client);
    }

    public function testAcceptsCustomClient(): void
    {
        $http = $this->makeMockHttpClient('{"jsonrpc":"2.0","result":"custom","id":1}');
        $client = new JsonRpcClient('http://example.com/rpc', $http);

        $result = $client->call('test');

        $this->assertSame('custom', $result);
    }

    public function testV11VersionPropagation(): void
    {
        $http = $this->makeMockHttpClient('{"version":"1.1","result":"ok","id":1}');
        $client = new JsonRpcClient(
            'http://example.com/rpc',
            $http,
            version: Version::V1_1,
        );

        $result = $client->call('test');

        $body = json_decode((string) $http->lastRequest->getBody(), true);
        $this->assertSame('1.1', $body['version']);
        $this->assertArrayNotHasKey('jsonrpc', $body);
        $this->assertSame('ok', $result);
    }

    public function testSetsJsonContentType(): void
    {
        $http = $this->makeMockHttpClient('{"jsonrpc":"2.0","result":null,"id":1}');
        $client = new JsonRpcClient('http://example.com/rpc', $http);

        $client->call('test');

        $this->assertSame('application/json', $http->lastRequest->getHeaderLine('Content-Type'));
        $this->assertSame('application/json', $http->lastRequest->getHeaderLine('Accept'));
    }
}
