<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\Transport;

use Horde\Http\Request;
use Horde\Http\RequestFactory;
use Horde\Http\Response;
use Horde\Http\StreamFactory;
use Horde\Rpc\JsonRpc\Exception\InternalErrorException;
use Horde\Rpc\JsonRpc\Protocol\Codec;
use Horde\Rpc\JsonRpc\Protocol\Version;
use Horde\Rpc\JsonRpc\Transport\HttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(HttpClient::class)]
class HttpClientTest extends TestCase
{
    private StreamFactory $streamFactory;
    private RequestFactory $requestFactory;

    protected function setUp(): void
    {
        $this->streamFactory = new StreamFactory();
        $this->requestFactory = new RequestFactory();
    }

    private function makeMockHttpClient(string $responseBody, int $statusCode = 200): ClientInterface
    {
        $streamFactory = $this->streamFactory;

        return new class ($responseBody, $statusCode, $streamFactory) implements ClientInterface {
            public ?RequestInterface $lastRequest = null;

            public function __construct(
                private readonly string $responseBody,
                private readonly int $statusCode,
                private readonly StreamFactory $sf,
            ) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->lastRequest = $request;
                $body = $this->sf->createStream($this->responseBody);

                return new Response($this->statusCode, body: $body);
            }
        };
    }

    public function testCallReturnsResult(): void
    {
        $httpClient = $this->makeMockHttpClient('{"jsonrpc":"2.0","result":42,"id":1}');
        $client = new HttpClient($httpClient, $this->requestFactory, $this->streamFactory, new Codec());

        $result = $client->call('http://example.com/rpc', 'math.add', [1, 2]);

        $this->assertSame(42, $result);
    }

    public function testCallSendsCorrectJson(): void
    {
        $httpClient = $this->makeMockHttpClient('{"jsonrpc":"2.0","result":null,"id":1}');
        $client = new HttpClient($httpClient, $this->requestFactory, $this->streamFactory, new Codec());

        $client->call('http://example.com/rpc', 'test.method', [1, 2]);

        $sentBody = json_decode((string) $httpClient->lastRequest->getBody(), true);
        $this->assertSame('2.0', $sentBody['jsonrpc']);
        $this->assertSame('test.method', $sentBody['method']);
        $this->assertSame([1, 2], $sentBody['params']);
        $this->assertArrayHasKey('id', $sentBody);
    }

    public function testCallSetsHeaders(): void
    {
        $httpClient = $this->makeMockHttpClient('{"jsonrpc":"2.0","result":null,"id":1}');
        $client = new HttpClient($httpClient, $this->requestFactory, $this->streamFactory, new Codec());

        $client->call('http://example.com/rpc', 'test');

        $this->assertSame('application/json', $httpClient->lastRequest->getHeaderLine('Content-Type'));
        $this->assertSame('application/json', $httpClient->lastRequest->getHeaderLine('Accept'));
    }

    public function testCallThrowsOnErrorResponse(): void
    {
        $httpClient = $this->makeMockHttpClient(
            '{"jsonrpc":"2.0","error":{"code":-32601,"message":"Method not found"},"id":1}'
        );
        $client = new HttpClient($httpClient, $this->requestFactory, $this->streamFactory, new Codec());

        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessage('Method not found');
        $client->call('http://example.com/rpc', 'nope');
    }

    public function testCallHandlesLegacyHttp500(): void
    {
        $httpClient = $this->makeMockHttpClient(
            '{"error":{"code":-32603,"message":"Server broke"}}',
            500,
        );
        $client = new HttpClient($httpClient, $this->requestFactory, $this->streamFactory, new Codec());

        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessage('Server broke');
        $client->call('http://example.com/rpc', 'test');
    }

    public function testNotifySendsNoId(): void
    {
        $httpClient = $this->makeMockHttpClient('', 204);
        $client = new HttpClient($httpClient, $this->requestFactory, $this->streamFactory, new Codec());

        $client->notify('http://example.com/rpc', 'log.event', ['something']);

        $sentBody = json_decode((string) $httpClient->lastRequest->getBody(), true);
        $this->assertSame('log.event', $sentBody['method']);
        $this->assertArrayNotHasKey('id', $sentBody);
    }

    public function testBatchSendsArray(): void
    {
        $responseBody = '[{"jsonrpc":"2.0","result":1,"id":1},{"jsonrpc":"2.0","result":2,"id":2}]';
        $httpClient = $this->makeMockHttpClient($responseBody);
        $client = new HttpClient($httpClient, $this->requestFactory, $this->streamFactory, new Codec());

        $results = $client->batch('http://example.com/rpc', [
            ['method' => 'a', 'params' => []],
            ['method' => 'b', 'params' => []],
        ]);

        $sentBody = json_decode((string) $httpClient->lastRequest->getBody(), true);
        $this->assertCount(2, $sentBody);

        $this->assertCount(2, $results);
    }

    public function testCallUsesV11WhenConfigured(): void
    {
        $httpClient = $this->makeMockHttpClient('{"version":"1.1","result":"ok","id":1}');
        $client = new HttpClient(
            $httpClient,
            $this->requestFactory,
            $this->streamFactory,
            new Codec(),
            Version::V1_1,
        );

        $result = $client->call('http://example.com/rpc', 'test');

        $sentBody = json_decode((string) $httpClient->lastRequest->getBody(), true);
        $this->assertSame('1.1', $sentBody['version']);
        $this->assertArrayNotHasKey('jsonrpc', $sentBody);
        $this->assertSame('ok', $result);
    }
}
