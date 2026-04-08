# JSON-RPC Usage Guide

The `Horde\Rpc\JsonRpc` namespace provides a modern JSON-RPC implementation
supporting both **JSON-RPC 1.1** and **2.0** protocols. It is built on top of
PSR-7 (HTTP Messages), PSR-14 (Event Dispatcher), PSR-15 (Server Handlers),
PSR-17 (HTTP Factories), and PSR-18 (HTTP Client).

## Quick Start

### Calling a JSON-RPC Server

```php
use Horde\Rpc\JsonRpc\JsonRpcClient;

$client = new JsonRpcClient('https://api.example.com/rpc');

// Single call
$result = $client->call('math.add', [3, 4]);
// => 7

// Notification (fire-and-forget, no response)
$client->notify('log.event', ['user logged in']);

// Batch (JSON-RPC 2.0 only)
$results = $client->batch([
    ['method' => 'math.add', 'params' => [1, 2]],
    ['method' => 'math.multiply', 'params' => [3, 4]],
    ['method' => 'log.event', 'params' => ['batch started'], 'notification' => true],
]);
```

By default, `JsonRpcClient` uses `Horde\Http\Client\Curl` as the HTTP
transport. You can inject any PSR-18 compliant client:

```php
use Horde\Rpc\JsonRpc\JsonRpcClient;

// Guzzle, Symfony HttpClient, or any PSR-18 implementation
$client = new JsonRpcClient(
    'https://api.example.com/rpc',
    $guzzleClient,
);
```

### Using JSON-RPC 1.1

```php
use Horde\Rpc\JsonRpc\JsonRpcClient;
use Horde\Rpc\JsonRpc\Protocol\Version;

$client = new JsonRpcClient(
    'https://legacy.example.com/rpc',
    version: Version::V1_1,
);
```

## Building a JSON-RPC Server

### Step 1: Create an API Provider

An API provider tells the dispatcher which methods are available and how to
call them. Implement `ApiProviderInterface` (method registry) and
`MethodInvokerInterface` (method execution).

The simplest approach is `CallableMapProvider` -- a name-to-callable map:

```php
use Horde\Rpc\JsonRpc\Dispatch\CallableMapProvider;

$provider = new CallableMapProvider([
    'ping'     => fn(): string => 'pong',
    'math.add' => fn(float $a, float $b): float => $a + $b,
    'user.get' => fn(int $id): array => $userRepository->find($id),
]);
```

### Step 2: Wire the Handler

Use the `JsonRpcHandler` facade to wire everything together:

```php
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Horde\Rpc\JsonRpc\JsonRpcHandler;

$handler = new JsonRpcHandler(
    provider: $provider,         // ApiProviderInterface
    invoker: $provider,          // MethodInvokerInterface (same object)
    responseFactory: new ResponseFactory(),
    streamFactory: new StreamFactory(),
    eventDispatcher: $eventDispatcher,  // PSR-14 EventDispatcherInterface
);
```

### Step 3: Handle Requests

The handler implements both `RequestHandlerInterface` and
`MiddlewareInterface`, so the same object works for direct routing
and in a middleware stack.

**As a PSR-15 RequestHandler** (direct routing):

```php
// $request is a PSR-7 ServerRequestInterface
$response = $handler->getHandler()->handle($request);
```

**As a PSR-15 Middleware** (in a middleware stack):

```php
// The handler auto-detects JSON-RPC requests by path and content type
$app->pipe($handler->getHandler());
```

The handler matches `POST` requests to the configured path with a JSON
content type. Non-matching requests pass through to the next handler.

## Writing a Custom Provider

### Using CallableMapProvider

For simple APIs, pass a map of method names to callables:

```php
use Horde\Rpc\JsonRpc\Dispatch\CallableMapProvider;
use Horde\Rpc\JsonRpc\Dispatch\MethodDescriptor;

$provider = new CallableMapProvider(
    // Method callables
    [
        'greet' => fn(string $name): string => "Hello, $name!",
        'time'  => fn(): string => date('c'),
    ],
    // Optional: explicit method descriptors for rpc.discover
    [
        'greet' => new MethodDescriptor(
            name: 'greet',
            description: 'Greet someone by name',
            parameters: [
                ['name' => 'name', 'type' => 'string', 'required' => true],
            ],
            returnType: 'string',
        ),
    ],
);
```

Methods without an explicit descriptor get a minimal auto-generated one
(name only, empty description).

### Implementing the Interfaces Directly

For more complex providers, implement the interfaces yourself. The
`MathApiProvider` included in this library demonstrates the pattern -- it
composes a `CallableMapProvider` internally:

```php
use Horde\Rpc\JsonRpc\Dispatch\ApiProviderInterface;
use Horde\Rpc\JsonRpc\Dispatch\MethodInvokerInterface;
use Horde\Rpc\JsonRpc\Dispatch\MethodDescriptor;
use Horde\Rpc\JsonRpc\Dispatch\Result;

final class MyApiProvider implements ApiProviderInterface, MethodInvokerInterface
{
    public function hasMethod(string $method): bool
    {
        return in_array($method, ['myapp.status', 'myapp.version'], true);
    }

    public function getMethodDescriptor(string $method): ?MethodDescriptor
    {
        return $this->hasMethod($method)
            ? new MethodDescriptor($method)
            : null;
    }

    public function listMethods(): array
    {
        return [
            new MethodDescriptor('myapp.status', 'Get application status'),
            new MethodDescriptor('myapp.version', 'Get application version'),
        ];
    }

    public function invoke(string $method, array $params): Result
    {
        return match ($method) {
            'myapp.status'  => new Result(['healthy' => true]),
            'myapp.version' => new Result('2.0.0'),
        };
    }
}
```

### The MathApiProvider Example

The library ships with `MathApiProvider` as a ready-to-use example:

```php
use Horde\Rpc\JsonRpc\Dispatch\MathApiProvider;
use Horde\Rpc\JsonRpc\JsonRpcHandler;

$math = MathApiProvider::create();

$handler = new JsonRpcHandler(
    provider: $math,
    invoker: $math,
    responseFactory: new \Horde\Http\ResponseFactory(),
    streamFactory: new \Horde\Http\StreamFactory(),
    eventDispatcher: $eventDispatcher,
);
```

It provides four methods with full descriptor metadata:

| Method          | Parameters       | Returns | Description                   |
|-----------------|------------------|---------|-------------------------------|
| `math.add`      | `a`, `b` (float) | float   | Add two numbers               |
| `math.subtract` | `a`, `b` (float) | float   | Subtract b from a             |
| `math.multiply` | `a`, `b` (float) | float   | Multiply two numbers          |
| `math.divide`   | `a`, `b` (float) | float   | Divide a by b                 |

`math.divide` throws `InvalidParamsException` on division by zero.

## Built-in System Methods

The `Dispatcher` provides two built-in fallback methods:

- **`rpc.discover`** -- Returns a service description listing all available
  methods with their descriptors (name, description, parameters, return type).
- **`rpc.ping`** -- Returns `"pong"`.

These are fallbacks: if your provider implements `rpc.discover` or `rpc.ping`
itself, the provider's implementation wins.

```php
$client = new JsonRpcClient('https://api.example.com/rpc');

// Discover available methods
$description = $client->call('rpc.discover');
// => ['methods' => [['name' => 'math.add', 'description' => '...', ...], ...]]

// Health check
$pong = $client->call('rpc.ping');
// => 'pong'
```

## Error Handling

### Client-Side

`JsonRpcClient` throws exceptions from the `Horde\Rpc\JsonRpc\Exception`
namespace. All implement `JsonRpcThrowable`:

```php
use Horde\Rpc\JsonRpc\Exception\InternalErrorException;
use Horde\Rpc\JsonRpc\Exception\JsonRpcThrowable;
use Psr\Http\Client\ClientExceptionInterface;

try {
    $result = $client->call('math.divide', [1, 0]);
} catch (JsonRpcThrowable $e) {
    // JSON-RPC error (method not found, invalid params, internal error, ...)
    echo $e->getMessage();
    echo $e->getJsonRpcCode(); // e.g. -32602
} catch (ClientExceptionInterface $e) {
    // HTTP transport error (connection refused, timeout, ...)
    echo $e->getMessage();
}
```

### Server-Side

Throw exceptions from the `Exception` namespace in your provider to return
structured JSON-RPC errors:

```php
use Horde\Rpc\JsonRpc\Exception\InvalidParamsException;
use Horde\Rpc\JsonRpc\Exception\MethodNotFoundException;

// In your invoke() method:
throw new InvalidParamsException('age must be positive');
// => {"error": {"code": -32602, "message": "age must be positive"}}
```

Any non-`JsonRpcThrowable` exception thrown by a provider is caught and
sanitized to a generic "Internal error" response. Internal details are never
leaked to the client.

| Exception                  | Code   | When to use                        |
|----------------------------|--------|------------------------------------|
| `ParseException`           | -32700 | Malformed JSON (handled by Codec)  |
| `InvalidRequestException`  | -32600 | Structurally invalid request       |
| `MethodNotFoundException`  | -32601 | Method does not exist              |
| `InvalidParamsException`   | -32602 | Wrong or invalid parameters        |
| `InternalErrorException`   | -32603 | Server-side processing failure     |
| `ServerErrorException`     | -32000 to -32099 | Application-defined errors  |

## Events (PSR-14)

The server handler emits events via a PSR-14 `EventDispatcherInterface`. Wire
up listeners for logging, metrics, or auditing:

| Event                  | When                                    | Key Properties              |
|------------------------|-----------------------------------------|-----------------------------|
| `RequestReceived`      | Valid request decoded                   | `request`                   |
| `RequestDispatched`    | Method executed successfully            | `request`, `result`, `duration` |
| `NotificationReceived` | Notification processed                  | `request`                   |
| `ErrorOccurred`        | Error response being sent               | `error`, `request`          |
| `BatchProcessing`      | Batch request starting                  | `requestCount`, `notificationCount` |

Events live in `Horde\Rpc\JsonRpc\Event\`.

## Horde Registry Migration

For existing Horde installations, `HordeRegistryApiProvider` bridges the old
`Horde_Rpc_Jsonrpc` behavior to the new adapter. It translates dot-notation
method names (`calendar.list`) to the slash-notation (`calendar/list`) that
`Horde_Registry` expects:

```php
use Horde\Rpc\JsonRpc\Dispatch\HordeRegistryApiProvider;
use Horde\Rpc\JsonRpc\JsonRpcHandler;

$provider = new HordeRegistryApiProvider($registry);

$handler = new JsonRpcHandler(
    provider: $provider,
    invoker: $provider,
    responseFactory: new \Horde\Http\ResponseFactory(),
    streamFactory: new \Horde\Http\StreamFactory(),
    eventDispatcher: $eventDispatcher,
);
```

## Architecture Overview

```
JSON-RPC Client     MCP Client (LLM)    SOAP Client
     |                    |                  |
JsonRpcClient       MCP Streamable     SoapClient (PHP)
     |              HTTP POST               |
     v                    v                  v
JsonRpcHandler       McpServer          SoapHandler
  => Codec             => McpRouter       => ext-soap
  => Dispatcher           |               => SoapCallHandler
  => HttpHandler          |                  |
     |                    |                  |
     +---------+----------+--------+---------+
               |                   |
        Shared Dispatch Layer
     ApiProviderInterface
     MethodInvokerInterface
     MethodDescriptor
```

All three protocol handlers implement both `RequestHandlerInterface` and
`MiddlewareInterface`. Stack them in a middleware pipeline and each one
claims its protocol or passes through:

```php
$app->pipe($soapHandler);      // text/xml → SOAP
$app->pipe($mcpHandler);       // /mcp + JSON → MCP
$app->pipe($jsonRpcHandler);   // /rpc/jsonrpc + JSON → JSON-RPC
```

Three layers with clear boundaries:

- **Protocol** (`Protocol\`) -- Wire format: Codec, Request, Response, Error,
  Batch, Version, ErrorCode
- **Dispatch** (`Dispatch\`) -- Business logic: provider interfaces,
  dispatcher, result types (shared with MCP and SOAP)
- **Transport** (`Transport\`) -- HTTP integration: PSR-15 dual-interface
  handler (handler + middleware), PSR-18 client

The dispatch layer is shared across all three protocols. Write your
provider once, serve it over JSON-RPC, MCP, and SOAP. See
[MCP-USAGE.md](MCP-USAGE.md) and [SOAP-USAGE.md](SOAP-USAGE.md).
