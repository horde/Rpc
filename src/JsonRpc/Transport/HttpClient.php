<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc\Transport;

use Horde\Rpc\JsonRpc\Exception\InternalErrorException;
use Horde\Rpc\JsonRpc\Exception\JsonRpcThrowable;
use Horde\Rpc\JsonRpc\Protocol\Codec;
use Horde\Rpc\JsonRpc\Protocol\Error;
use Horde\Rpc\JsonRpc\Protocol\ErrorCode;
use Horde\Rpc\JsonRpc\Protocol\Request;
use Horde\Rpc\JsonRpc\Protocol\Response;
use Horde\Rpc\JsonRpc\Protocol\Version;
use JsonException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * JSON-RPC client using PSR-18 HTTP client.
 *
 * Handles both modern (HTTP 200 with error body) and legacy (HTTP 500)
 * server responses.
 */
final class HttpClient
{
    private int $idCounter = 0;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly Codec $codec,
        private readonly Version $version = Version::V2_0,
    ) {}

    /**
     * Send a JSON-RPC request and return the decoded result.
     *
     * @throws JsonRpcThrowable On JSON-RPC error response
     * @throws \Psr\Http\Client\ClientExceptionInterface On HTTP transport failure
     */
    public function call(string $url, string $method, array $params = []): mixed
    {
        $request = new Request(
            $this->version,
            $method,
            $params,
            ++$this->idCounter,
        );

        $responseBody = $this->send($url, $this->codec->encodeRequest($request));

        return $this->parseResponse($responseBody);
    }

    /**
     * Send a notification (no response expected).
     *
     * @throws \Psr\Http\Client\ClientExceptionInterface On HTTP transport failure
     */
    public function notify(string $url, string $method, array $params = []): void
    {
        $request = new Request(
            $this->version,
            $method,
            $params,
            null,
        );

        $this->send($url, $this->codec->encodeRequest($request));
    }

    /**
     * Send a batch of requests (2.0 only).
     *
     * @param list<array{method: string, params?: array, notification?: bool}> $requests
     * @return list<Response|Error>
     * @throws \Psr\Http\Client\ClientExceptionInterface On HTTP transport failure
     */
    public function batch(string $url, array $requests): array
    {
        $rpcRequests = [];
        foreach ($requests as $entry) {
            $isNotification = $entry['notification'] ?? false;
            $rpcRequests[] = new Request(
                Version::V2_0,
                $entry['method'],
                $entry['params'] ?? [],
                $isNotification ? null : ++$this->idCounter,
            );
        }

        $jsonParts = [];
        foreach ($rpcRequests as $req) {
            $jsonParts[] = json_decode(
                $this->codec->encodeRequest($req),
                flags: JSON_THROW_ON_ERROR,
            );
        }
        $json = json_encode($jsonParts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $responseBody = $this->send($url, $json);

        return $this->parseBatchResponse($responseBody);
    }

    private function send(string $url, string $json): string
    {
        $body = $this->streamFactory->createStream($json);
        $httpRequest = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', 'Horde JSON-RPC Client')
            ->withBody($body);

        $httpResponse = $this->httpClient->sendRequest($httpRequest);
        $statusCode = $httpResponse->getStatusCode();
        $responseBody = (string) $httpResponse->getBody();

        if ($statusCode === 204) {
            return '';
        }

        // Handle legacy servers that return HTTP 500 for JSON-RPC errors
        if ($statusCode === 500) {
            $this->handleLegacyErrorResponse($responseBody);
        }

        if ($statusCode !== 200) {
            throw new InternalErrorException("HTTP $statusCode: $responseBody");
        }

        return $responseBody;
    }

    private function parseResponse(string $body): mixed
    {
        if ($body === '') {
            return null;
        }

        try {
            $decoded = json_decode($body, associative: false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InternalErrorException('Invalid JSON in response: ' . $e->getMessage(), previous: $e);
        }

        if (isset($decoded->error)) {
            $this->throwFromErrorObject($decoded->error);
        }

        return $decoded->result ?? null;
    }

    /**
     * @return list<Response|Error>
     */
    private function parseBatchResponse(string $body): array
    {
        if ($body === '') {
            return [];
        }

        try {
            $decoded = json_decode($body, associative: false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InternalErrorException('Invalid JSON in batch response: ' . $e->getMessage(), previous: $e);
        }

        if (!is_array($decoded)) {
            throw new InternalErrorException('Batch response must be a JSON array');
        }

        $results = [];
        foreach ($decoded as $item) {
            if (isset($item->error)) {
                $code = $item->error->code ?? ErrorCode::InternalError->value;
                $message = $item->error->message ?? 'Unknown error';
                $data = $item->error->data ?? null;
                $results[] = new Error(
                    Version::V2_0,
                    ErrorCode::tryFrom($code) ?? $code,
                    $message,
                    $data,
                    $item->id ?? null,
                );
            } else {
                $results[] = new Response(
                    Version::V2_0,
                    $item->result ?? null,
                    $item->id ?? 0,
                );
            }
        }

        return $results;
    }

    private function handleLegacyErrorResponse(string $body): never
    {
        try {
            $decoded = json_decode($body, associative: false, flags: JSON_THROW_ON_ERROR);
            if (isset($decoded->error)) {
                $this->throwFromErrorObject($decoded->error);
            }
        } catch (JsonException) {
            // Not valid JSON, fall through
        }

        throw new InternalErrorException($body ?: 'Server error (HTTP 500)');
    }

    private function throwFromErrorObject(object $error): never
    {
        $message = $error->message ?? 'Unknown error';
        $code = $error->code ?? ErrorCode::InternalError->value;

        throw new InternalErrorException($message, $code);
    }
}
