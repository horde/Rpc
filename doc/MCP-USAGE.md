# MCP Server Usage Guide

The `Horde\Rpc\Mcp` namespace provides a Model Context Protocol (MCP) server
implementation. MCP is built on JSON-RPC 2.0 and allows AI language models to
discover and invoke tools and read resources exposed by your application.

The MCP layer shares the same dispatch interfaces (`ApiProviderInterface`,
`MethodInvokerInterface`) as the JSON-RPC adapter, so the same provider code
serves both protocols.

## Quick Start

### MCP Server with MathApiProvider

```php
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Horde\Rpc\JsonRpc\Dispatch\MathApiProvider;
use Horde\Rpc\Mcp\McpServer;
use Horde\Rpc\Mcp\Protocol\ServerInfo;

$math = MathApiProvider::create();

$server = new McpServer(
    new ServerInfo('horde-math', '1.0.0', 'Math tools for LLMs'),
    $math,
    $math,
    new ResponseFactory(),
    new StreamFactory(),
);

// As a PSR-15 handler (direct routing)
$response = $server->getHandler()->handle($request);

// As PSR-15 middleware (in a middleware stack)
// The handler auto-detects MCP requests by path and content type
$app->pipe($server->getHandler());
```

An MCP client (like Claude) can now discover and call the math tools:

```
Client → POST /mcp
{"jsonrpc":"2.0","method":"tools/list","id":1}

Server →
{"jsonrpc":"2.0","result":{"tools":[
  {"name":"math.add","description":"Add two numbers","inputSchema":{...}},
  {"name":"math.subtract",...},
  {"name":"math.multiply",...},
  {"name":"math.divide",...}
]},"id":1}

Client → POST /mcp
{"jsonrpc":"2.0","method":"tools/call","params":{"name":"math.add","arguments":[3,4]},"id":2}

Server →
{"jsonrpc":"2.0","result":{"content":[{"type":"text","text":"7"}],"isError":false},"id":2}
```

## Creating Tools from Existing Providers

Any class implementing `ApiProviderInterface` + `MethodInvokerInterface` works
as an MCP tool provider. No MCP-specific code is needed in the provider itself.

### Using CallableMapProvider

```php
use Horde\Rpc\JsonRpc\Dispatch\CallableMapProvider;
use Horde\Rpc\JsonRpc\Dispatch\MethodDescriptor;
use Horde\Rpc\Mcp\McpServer;
use Horde\Rpc\Mcp\Protocol\ServerInfo;

$provider = new CallableMapProvider(
    [
        'weather.get' => fn(string $city) => fetchWeather($city),
        'time.now'    => fn() => date('c'),
    ],
    [
        'weather.get' => new MethodDescriptor(
            'weather.get',
            'Get weather for a city',
            [['name' => 'city', 'type' => 'string', 'required' => true]],
            'string',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'city' => ['type' => 'string', 'description' => 'City name'],
                ],
                'required' => ['city'],
            ],
        ),
    ],
);

$server = new McpServer(
    new ServerInfo('my-tools', '1.0.0'),
    $provider, $provider,
    new ResponseFactory(), new StreamFactory(),
);
```

### MethodDescriptor and MCP Tool Schema

The `MethodDescriptor` bridges to MCP's tool format. The key fields for MCP:

| Field | Purpose | MCP Mapping |
|-------|---------|-------------|
| `name` | Tool name | `tools/list → name` |
| `description` | Tool description | `tools/list → description` |
| `inputSchema` | JSON Schema for input | `tools/list → inputSchema` |
| `outputSchema` | JSON Schema for output | `tools/list → outputSchema` |
| `parameters` | Parameter metadata | Auto-generates `inputSchema` if not set |
| `permissions` | Required permissions | Checked before `tools/call` |

If you provide `inputSchema` explicitly, it is used as-is. If omitted, it is
auto-generated from the `parameters` array with PHP-to-JSON-Schema type mapping.

## Serving Both JSON-RPC and MCP

The same provider can serve both protocols simultaneously:

```php
use Horde\Rpc\JsonRpc\Dispatch\MathApiProvider;
use Horde\Rpc\JsonRpc\JsonRpcHandler;
use Horde\Rpc\Mcp\McpServer;
use Horde\Rpc\Mcp\Protocol\ServerInfo;

$math = MathApiProvider::create();

// JSON-RPC endpoint
$jsonRpcHandler = new JsonRpcHandler(
    $math, $math,
    $responseFactory, $streamFactory, $eventDispatcher,
);

// MCP endpoint
$mcpServer = new McpServer(
    new ServerInfo('horde-math', '1.0.0'),
    $math, $math,
    $responseFactory, $streamFactory,
);

// Wire into middleware stack
$app->pipe($jsonRpcHandler->getMiddleware('/rpc/jsonrpc'));
$app->pipe($mcpServer->getMiddleware('/mcp'));
```

## Resources

MCP resources provide read-only data for context. Implement
`ResourceProviderInterface`:

```php
use Horde\Rpc\Mcp\Protocol\ResourceContent;
use Horde\Rpc\Mcp\Protocol\ResourceDescriptor;
use Horde\Rpc\Mcp\ResourceProviderInterface;

class DocsResourceProvider implements ResourceProviderInterface
{
    public function listResources(): array
    {
        return [
            new ResourceDescriptor(
                'docs://api/overview',
                'API Overview',
                'Overview of the API endpoints',
                'text/markdown',
            ),
        ];
    }

    public function hasResource(string $uri): bool
    {
        return $uri === 'docs://api/overview';
    }

    public function readResource(string $uri): ResourceContent
    {
        return new ResourceContent(
            $uri,
            text: '# API Overview\n\nThis API provides...',
            mimeType: 'text/markdown',
        );
    }
}
```

Wire it into the server:

```php
$server = new McpServer(
    new ServerInfo('my-app', '1.0.0'),
    $provider, $provider,
    $responseFactory, $streamFactory,
    resourceProvider: new DocsResourceProvider(),
);
```

The server automatically advertises `resources` capability when a resource
provider is given.

## Authentication and Permissions

### Per-Method Permissions

Following Horde's `$_noPerms` pattern, methods declare their permission
requirements in `MethodDescriptor::$permissions`:

```php
// Public method (no auth required) -- like being in $_noPerms
new MethodDescriptor('time.now')
// permissions defaults to [] = public

// Protected method (requires authentication)
new MethodDescriptor(
    'admin.reset',
    'Reset application state',
    permissions: ['horde:admin'],
)
```

The MCP router checks permissions before invoking a tool. If the caller lacks
the required permissions, a JSON-RPC error (-32603 "Unauthorized") is returned.

### How Auth Context Works

The `AuthContext` is extracted from PSR-7 request attributes set by upstream
auth middleware:

| Attribute | Type | Purpose |
|-----------|------|---------|
| `auth_permissions` | `string[]` | Granted permission identifiers |
| `authenticated` | `bool` | Whether the user is authenticated |

Wire your auth middleware before the MCP handler in your middleware stack:

```php
// Auth middleware sets request attributes
$app->pipe($jwtAuthMiddleware);       // sets auth_permissions, authenticated
$app->pipe($mcpServer->getMiddleware('/mcp'));
```

### Public vs Protected Tools

```php
$provider = new CallableMapProvider(
    [
        'ping'         => fn() => 'pong',           // public
        'admin.reset'  => fn() => resetApp(),        // protected
    ],
    [
        'ping' => new MethodDescriptor('ping'),
        // permissions = [] → public, no auth check

        'admin.reset' => new MethodDescriptor(
            'admin.reset',
            permissions: ['horde:admin'],
        ),
        // permissions = ['horde:admin'] → requires auth
    ],
);
```

## MCP Protocol Lifecycle

The MCP handshake follows this sequence:

```
Client → {"jsonrpc":"2.0","method":"initialize","params":{...},"id":1}
Server → {"jsonrpc":"2.0","result":{"protocolVersion":"2025-11-25","capabilities":{...},"serverInfo":{...}},"id":1}
Client → {"jsonrpc":"2.0","method":"notifications/initialized"}
Server → 202 Accepted
```

After initialization, the client can call `tools/list`, `tools/call`,
`resources/list`, `resources/read`, and `ping`.

## Architecture Overview

```
JSON-RPC Client     MCP Client (LLM)    SOAP Client
     |                    |                  |
JsonRpcClient       MCP Streamable     SoapClient (PHP)
     |              HTTP POST               |
     v                    v                  v
JsonRpcHandler       McpServer          SoapHandler
  => Codec           (facade)            => ext-soap
  => Dispatcher        => McpRouter      => SoapCallHandler
  => HttpHandler          => tools/list     |
     |                    => tools/call     |
     |                    => resources/*    |
     +---------+----------+--------+--------+
               |                   |
        Shared Dispatch Layer
     ApiProviderInterface
     MethodInvokerInterface
     MethodDescriptor
     CallableMapProvider
     MathApiProvider
     HordeRegistryApiProvider
```

Three protocols, one dispatch layer. Write your provider once, serve it
over JSON-RPC, MCP, and SOAP. All handlers implement both
`RequestHandlerInterface` and `MiddlewareInterface` for flexible stacking.
