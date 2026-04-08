<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc;

use Horde\Http\Client\Curl;
use Horde\Http\Client\Options;
use Horde\Http\RequestFactory;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Horde\Rpc\JsonRpc\Protocol\Codec;
use Horde\Rpc\JsonRpc\Protocol\Error;
use Horde\Rpc\JsonRpc\Protocol\Response;
use Horde\Rpc\JsonRpc\Protocol\Version;
use Horde\Rpc\JsonRpc\Transport\HttpClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Convenience facade for JSON-RPC client calls.
 *
 * Wires Codec, PSR-17 factories, and a PSR-18 HTTP client together,
 * defaulting to Horde\Http\Client\Curl when no client is provided.
 *
 * Example usage:
 *
 *     $client = new JsonRpcClient('https://api.example.com/rpc');
 *     $result = $client->call('math.add', [3, 4]);
 *
 *     // With a custom PSR-18 client:
 *     $client = new JsonRpcClient('https://api.example.com/rpc', $guzzleClient);
 */
final class JsonRpcClient
{
    private readonly HttpClient $transport;

    public function __construct(
        private readonly string $url,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        Version $version = Version::V2_0,
    ) {
        $streamFactory ??= new StreamFactory();
        $requestFactory ??= new RequestFactory();

        if ($httpClient === null) {
            $responseFactory = new ResponseFactory();
            $httpClient = new Curl($responseFactory, $streamFactory, new Options());
        }

        $this->transport = new HttpClient(
            $httpClient,
            $requestFactory,
            $streamFactory,
            new Codec(),
            $version,
        );
    }

    /**
     * Send a JSON-RPC request and return the result.
     *
     * @throws \Horde\Rpc\JsonRpc\Exception\JsonRpcThrowable On JSON-RPC error
     * @throws \Psr\Http\Client\ClientExceptionInterface On HTTP transport failure
     */
    public function call(string $method, array $params = []): mixed
    {
        return $this->transport->call($this->url, $method, $params);
    }

    /**
     * Send a JSON-RPC notification (no response expected).
     *
     * @throws \Psr\Http\Client\ClientExceptionInterface On HTTP transport failure
     */
    public function notify(string $method, array $params = []): void
    {
        $this->transport->notify($this->url, $method, $params);
    }

    /**
     * Send a batch of JSON-RPC requests (2.0 only).
     *
     * @param list<array{method: string, params?: array, notification?: bool}> $requests
     * @return list<Response|Error>
     * @throws \Psr\Http\Client\ClientExceptionInterface On HTTP transport failure
     */
    public function batch(array $requests): array
    {
        return $this->transport->batch($this->url, $requests);
    }
}
