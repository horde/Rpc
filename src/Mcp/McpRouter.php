<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Mcp;

use Horde\Rpc\JsonRpc\Dispatch\ApiProviderInterface;
use Horde\Rpc\JsonRpc\Dispatch\MethodInvokerInterface;
use Horde\Rpc\Mcp\Protocol\ServerCapabilities;
use Horde\Rpc\Mcp\Protocol\ServerInfo;
use Horde\Rpc\Mcp\Protocol\ToolDescriptor;
use JsonException;

/**
 * Routes MCP JSON-RPC methods to the appropriate handler.
 *
 * Maps MCP protocol methods (initialize, tools/list, tools/call,
 * resources/list, resources/read) to our dispatch layer interfaces.
 */
final class McpRouter
{
    private const PROTOCOL_VERSION = '2025-11-25';

    public function __construct(
        private readonly ServerInfo $serverInfo,
        private readonly ServerCapabilities $capabilities,
        private readonly ApiProviderInterface $provider,
        private readonly MethodInvokerInterface $invoker,
        private readonly ?ResourceProviderInterface $resourceProvider = null,
    ) {}

    /**
     * Route a decoded JSON-RPC message and return the result payload.
     *
     * @param string $method JSON-RPC method name
     * @param array|object $params Method parameters
     * @param AuthContext $authContext Auth context from transport
     * @return array Result payload (goes into JSON-RPC "result" field)
     * @throws McpError For protocol errors
     */
    public function route(string $method, array|object $params, AuthContext $authContext): array
    {
        $params = (array) $params;

        return match ($method) {
            'initialize' => $this->handleInitialize($params),
            'ping' => $this->handlePing(),
            'tools/list' => $this->handleToolsList(),
            'tools/call' => $this->handleToolsCall($params, $authContext),
            'resources/list' => $this->handleResourcesList(),
            'resources/read' => $this->handleResourcesRead($params),
            default => throw McpError::methodNotFound($method),
        };
    }

    /**
     * Check if a method is a notification (no response expected).
     */
    public function isNotification(string $method): bool
    {
        return $method === 'notifications/initialized'
            || str_starts_with($method, 'notifications/');
    }

    private function handleInitialize(array $params): array
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => $this->capabilities->toArray(),
            'serverInfo' => $this->serverInfo->toArray(),
        ];
    }

    private function handlePing(): array
    {
        return [];
    }

    private function handleToolsList(): array
    {
        $tools = [];
        foreach ($this->provider->listMethods() as $descriptor) {
            $tools[] = ToolDescriptor::fromMethodDescriptor($descriptor)->toArray();
        }

        return ['tools' => $tools];
    }

    private function handleToolsCall(array $params, AuthContext $authContext): array
    {
        $name = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        if (!$this->provider->hasMethod($name)) {
            throw McpError::toolNotFound($name);
        }

        // Per-method auth check
        $descriptor = $this->provider->getMethodDescriptor($name);
        if ($descriptor !== null && !empty($descriptor->permissions)) {
            if (!$authContext->hasAllPermissions($descriptor->permissions)) {
                throw McpError::unauthorized($name);
            }
        }

        try {
            $result = $this->invoker->invoke($name, (array) $arguments);
            $encoded = json_encode($result->value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            return [
                'content' => [
                    ['type' => 'text', 'text' => $encoded],
                ],
                'isError' => false,
            ];
        } catch (\Throwable $e) {
            return [
                'content' => [
                    ['type' => 'text', 'text' => $e->getMessage()],
                ],
                'isError' => true,
            ];
        }
    }

    private function handleResourcesList(): array
    {
        if ($this->resourceProvider === null) {
            throw McpError::methodNotFound('resources/list');
        }

        $resources = [];
        foreach ($this->resourceProvider->listResources() as $desc) {
            $resources[] = $desc->toArray();
        }

        return ['resources' => $resources];
    }

    private function handleResourcesRead(array $params): array
    {
        if ($this->resourceProvider === null) {
            throw McpError::methodNotFound('resources/read');
        }

        $uri = $params['uri'] ?? '';
        if (!$this->resourceProvider->hasResource($uri)) {
            throw McpError::resourceNotFound($uri);
        }

        $content = $this->resourceProvider->readResource($uri);

        return ['contents' => [$content->toArray()]];
    }
}
