<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Mcp\Transport;

use Horde\Rpc\Mcp\AuthContext;
use Horde\Rpc\Mcp\McpError;
use Horde\Rpc\Mcp\McpRouter;
use JsonException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 handler and middleware for MCP Streamable HTTP transport.
 *
 * Implements both RequestHandlerInterface (direct routing) and
 * MiddlewareInterface (protocol detection in a middleware stack).
 *
 * As middleware: detects MCP requests by method, path, and content
 * type, handles matching requests or passes through.
 *
 * As handler: processes the request as MCP unconditionally.
 */
final class HttpHandler implements RequestHandlerInterface, MiddlewareInterface
{
    private const SUPPORTED_VERSIONS = ['2025-11-25', '2025-03-26'];
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    public function __construct(
        private readonly McpRouter $router,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $path = '/mcp',
    ) {}

    /**
     * PSR-15 MiddlewareInterface: detect MCP request or pass through.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $next,
    ): ResponseInterface {
        if ($this->matches($request)) {
            return $this->handleMcp($request);
        }

        return $next->handle($request);
    }

    /**
     * PSR-15 RequestHandlerInterface: handle request as MCP.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handleMcp($request);
    }

    /**
     * Check whether this request looks like an MCP request.
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

    private function handleMcp(ServerRequestInterface $request): ResponseInterface
    {
        $body = (string) $request->getBody();

        try {
            $decoded = json_decode($body, associative: false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->jsonRpcError(null, -32700, 'Parse error');
        }

        if (!($decoded instanceof \stdClass)) {
            return $this->jsonRpcError(null, -32600, 'Invalid Request');
        }

        $method = $decoded->method ?? null;
        $params = $decoded->params ?? [];
        $id = $decoded->id ?? null;

        if (!is_string($method)) {
            return $this->jsonRpcError($id, -32600, 'Invalid Request: method must be a string');
        }

        // Notifications: no id, no response body
        if ($id === null || $this->router->isNotification($method)) {
            return $this->responseFactory->createResponse(202);
        }

        $authContext = $this->extractAuthContext($request);

        try {
            $result = $this->router->route($method, $params, $authContext);
        } catch (McpError $e) {
            return $this->jsonRpcError($id, $e->getJsonRpcCode(), $e->getMessage(), $e->getErrorData());
        }

        return $this->jsonRpcResult($id, $result);
    }

    private function extractAuthContext(ServerRequestInterface $request): AuthContext
    {
        $permissions = $request->getAttribute('auth_permissions', []);
        $authenticated = $request->getAttribute('authenticated', false);

        if (!is_array($permissions)) {
            $permissions = [];
        }

        return new AuthContext($permissions, (bool) $authenticated);
    }

    private function jsonRpcResult(string|int $id, mixed $result): ResponseInterface
    {
        $payload = [
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id,
        ];

        return $this->jsonResponse($payload);
    }

    private function jsonRpcError(
        string|int|null $id,
        int $code,
        string $message,
        mixed $data = null,
    ): ResponseInterface {
        $error = ['code' => $code, 'message' => $message];
        if ($data !== null) {
            $error['data'] = $data;
        }

        $payload = [
            'jsonrpc' => '2.0',
            'error' => $error,
            'id' => $id,
        ];

        return $this->jsonResponse($payload);
    }

    private function jsonResponse(array $payload): ResponseInterface
    {
        $json = json_encode($payload, self::JSON_FLAGS);
        $body = $this->streamFactory->createStream($json);

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }
}
