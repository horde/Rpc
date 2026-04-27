<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc\Transport;

use Horde\Rpc\Dispatch\Result;
use Horde\Rpc\JsonRpc\Dispatch\DispatcherInterface;
use Horde\Rpc\JsonRpc\Event\BatchProcessing;
use Horde\Rpc\JsonRpc\Event\ErrorOccurred;
use Horde\Rpc\JsonRpc\Event\NotificationReceived;
use Horde\Rpc\JsonRpc\Event\RequestDispatched;
use Horde\Rpc\JsonRpc\Event\RequestReceived;
use Horde\Rpc\JsonRpc\Exception\InvalidRequestException;
use Horde\Rpc\JsonRpc\Exception\JsonRpcThrowable;
use Horde\Rpc\JsonRpc\Exception\ParseException;
use Horde\Rpc\JsonRpc\Protocol\Batch;
use Horde\Rpc\JsonRpc\Protocol\Codec;
use Horde\Rpc\JsonRpc\Protocol\Error;
use Horde\Rpc\JsonRpc\Protocol\ErrorCode;
use Horde\Rpc\JsonRpc\Protocol\Request;
use Horde\Rpc\JsonRpc\Protocol\Response;
use Horde\Rpc\JsonRpc\Protocol\Version;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * PSR-15 handler and middleware for JSON-RPC over HTTP.
 *
 * Implements both RequestHandlerInterface (direct routing) and
 * MiddlewareInterface (protocol detection in a middleware stack).
 *
 * As middleware: detects JSON-RPC requests by method, path, and
 * content type, handles matching requests or passes through.
 *
 * As handler: processes the request as JSON-RPC unconditionally.
 *
 * Returns HTTP 200 for all JSON-RPC responses (success and error),
 * HTTP 204 for JSON-RPC 2.0 notifications.
 */
final class HttpHandler implements RequestHandlerInterface, MiddlewareInterface
{
    public function __construct(
        private readonly Codec $codec,
        private readonly DispatcherInterface $dispatcher,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly int $maxBatchSize = 100,
        private readonly string $path = '/rpc/jsonrpc',
    ) {}

    /**
     * PSR-15 MiddlewareInterface: detect JSON-RPC request or pass through.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $next,
    ): ResponseInterface {
        if ($this->matches($request)) {
            return $this->handleJsonRpc($request);
        }

        return $next->handle($request);
    }

    /**
     * PSR-15 RequestHandlerInterface: handle request as JSON-RPC.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handleJsonRpc($request);
    }

    /**
     * Check whether this request looks like a JSON-RPC request.
     *
     * Matches: POST + configured path + JSON content type.
     */
    private function matches(ServerRequestInterface $request): bool
    {
        if ($request->getMethod() !== 'POST') {
            return false;
        }

        if ($request->getUri()->getPath() !== $this->path) {
            return false;
        }

        $contentType = $request->getHeaderLine('Content-Type');

        return str_contains($contentType, 'json');
    }

    private function handleJsonRpc(ServerRequestInterface $request): ResponseInterface
    {
        $body = (string) $request->getBody();

        try {
            $decoded = $this->codec->decode($body);
        } catch (ParseException $e) {
            return $this->handleDecodeError($e, Version::V2_0);
        } catch (InvalidRequestException $e) {
            return $this->handleDecodeError($e, Version::V2_0);
        }

        if ($decoded instanceof Batch) {
            return $this->handleBatch($decoded);
        }

        return $this->handleSingleRequest($decoded);
    }

    private function handleSingleRequest(Request $request): ResponseInterface
    {
        if ($request->isNotification()) {
            return $this->handleNotification($request);
        }

        $startTime = microtime(true);
        $this->eventDispatcher->dispatch(
            new RequestReceived($request, $request->version, $startTime)
        );

        try {
            $result = $this->dispatcher->dispatch($request);
        } catch (Throwable $e) {
            return $this->handleDispatchError($e, $request);
        }

        $duration = microtime(true) - $startTime;
        $this->eventDispatcher->dispatch(
            new RequestDispatched($request, $result->value, $duration)
        );

        $response = new Response($request->version, $result->value, $request->id);

        return $this->jsonResponse($this->codec->encodeResponse($response));
    }

    private function handleNotification(Request $request): ResponseInterface
    {
        $this->eventDispatcher->dispatch(new NotificationReceived($request));

        try {
            $result = $this->dispatcher->dispatch($request);
        } catch (Throwable) {
            // Notifications: errors are silently discarded per spec
        }

        if ($request->version === Version::V2_0) {
            return $this->emptyResponse();
        }

        // V1_1: return the result even for notifications (ambiguous spec)
        $response = new Response($request->version, $result->value ?? null, 0);

        return $this->jsonResponse($this->codec->encodeResponse($response));
    }

    private function handleBatch(Batch $batch): ResponseInterface
    {
        if (count($batch) > $this->maxBatchSize) {
            $error = new Error(
                Version::V2_0,
                ErrorCode::InvalidRequest,
                "Batch size exceeds maximum of $this->maxBatchSize",
            );
            $this->eventDispatcher->dispatch(new ErrorOccurred($error));

            return $this->jsonResponse($this->codec->encodeError($error));
        }

        $requestCount = 0;
        $notificationCount = 0;
        foreach ($batch as $item) {
            if ($item instanceof Request) {
                if ($item->isNotification()) {
                    $notificationCount++;
                } else {
                    $requestCount++;
                }
            }
        }

        $this->eventDispatcher->dispatch(
            new BatchProcessing($batch, $requestCount, $notificationCount)
        );

        $responses = [];
        foreach ($batch as $item) {
            if ($item instanceof Error) {
                // Decode failure from batch parsing
                $responses[] = $item;
                continue;
            }

            if ($item->isNotification()) {
                $this->eventDispatcher->dispatch(new NotificationReceived($item));
                try {
                    $this->dispatcher->dispatch($item);
                } catch (Throwable) {
                    // Notifications: errors silently discarded
                }
                continue; // No response for notifications
            }

            $startTime = microtime(true);
            $this->eventDispatcher->dispatch(
                new RequestReceived($item, $item->version, $startTime)
            );

            try {
                $result = $this->dispatcher->dispatch($item);
                $duration = microtime(true) - $startTime;
                $this->eventDispatcher->dispatch(
                    new RequestDispatched($item, $result->value, $duration)
                );
                $responses[] = new Response($item->version, $result->value, $item->id);
            } catch (Throwable $e) {
                $errorObj = $this->exceptionToError($e, $item);
                $this->eventDispatcher->dispatch(
                    new ErrorOccurred($errorObj, $e, $item)
                );
                $responses[] = $errorObj;
            }
        }

        if ($responses === []) {
            return $this->emptyResponse();
        }

        return $this->jsonResponse($this->codec->encodeBatch($responses));
    }

    private function handleDecodeError(
        JsonRpcThrowable $e,
        Version $version,
    ): ResponseInterface {
        $error = new Error(
            $version,
            $e->getJsonRpcCode(),
            $e->getMessage(),
            $e->getErrorData(),
        );
        $this->eventDispatcher->dispatch(new ErrorOccurred($error, $e));

        return $this->jsonResponse($this->codec->encodeError($error));
    }

    private function handleDispatchError(
        Throwable $e,
        Request $request,
    ): ResponseInterface {
        $error = $this->exceptionToError($e, $request);
        $this->eventDispatcher->dispatch(new ErrorOccurred($error, $e, $request));

        return $this->jsonResponse($this->codec->encodeError($error));
    }

    /**
     * Map an exception to a JSON-RPC Error value object.
     *
     * JsonRpcThrowable exceptions expose their code and message directly.
     * All other exceptions are sanitized to a generic "Internal error"
     * to prevent leaking internal details.
     */
    private function exceptionToError(Throwable $e, Request $request): Error
    {
        if ($e instanceof JsonRpcThrowable) {
            return new Error(
                $request->version,
                $e->getJsonRpcCode(),
                $e->getMessage(),
                $e->getErrorData(),
                $request->id,
            );
        }

        return new Error(
            $request->version,
            ErrorCode::InternalError,
            'Internal error',
            null,
            $request->id,
        );
    }

    private function jsonResponse(string $json, int $status = 200): ResponseInterface
    {
        $body = $this->streamFactory->createStream($json);

        return $this->responseFactory->createResponse($status)
            ->withBody($body)
            ->withHeader('Content-Type', 'application/json');
    }

    private function emptyResponse(): ResponseInterface
    {
        return $this->responseFactory->createResponse(204);
    }
}
