# SOAP Usage Guide

The `Horde\Rpc\Soap` namespace provides a SOAP server implementation built
on PHP's ext-soap, PSR-7 (HTTP Messages), and PSR-15 (Server Handlers).

The SOAP handler shares the same dispatch interfaces (`ApiProviderInterface`,
`MethodInvokerInterface`) as JSON-RPC and MCP, so the same provider code
serves all three protocols.

## Requirements

SOAP support requires the PHP `soap` extension (`ext-soap`). The extension
is **optional** at the library level -- the handler returns a meaningful
SOAP Fault response (HTTP 501) when ext-soap is not loaded, and does not
interfere with other protocols in the middleware stack.

## Quick Start

```php
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Horde\Rpc\JsonRpc\Dispatch\MathApiProvider;
use Horde\Rpc\Soap\SoapHandler;

$math = MathApiProvider::create();

$handler = new SoapHandler(
    provider: $math,
    invoker: $math,
    responseFactory: new ResponseFactory(),
    streamFactory: new StreamFactory(),
);

// As a PSR-15 handler (direct routing)
$response = $handler->handle($request);

// As PSR-15 middleware (in a middleware stack)
// Detects SOAP requests by Content-Type (text/xml, application/soap+xml)
$app->pipe($handler);
```

## SOAP Detection

The handler identifies SOAP requests by:

1. **HTTP Method**: `POST`
2. **Path**: matches the configured path (default: `/rpc/soap`)
3. **Content-Type**: `text/xml` (SOAP 1.1) or `application/soap+xml` (SOAP 1.2)

The `SOAPAction` header is accepted when present but not required for
matching (SOAP 1.2 may omit it).

## Graceful Degradation

When ext-soap is not loaded, the handler:

- **As middleware**: still matches SOAP requests by Content-Type and path,
  but returns a SOAP Fault XML response with HTTP 501
- **As handler**: returns the same 501 SOAP Fault
- **Does not interfere** with JSON-RPC or MCP handlers in the stack

```xml
<!-- Response when ext-soap is unavailable -->
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
  <SOAP-ENV:Body>
    <SOAP-ENV:Fault>
      <faultcode>SOAP-ENV:Server</faultcode>
      <faultstring>SOAP support requires the PHP soap extension</faultstring>
    </SOAP-ENV:Fault>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
```

## Stacking All Three Protocols

All protocol handlers implement both `RequestHandlerInterface` and
`MiddlewareInterface`. Stack them in a middleware pipeline -- each one
claims its protocol or passes through:

```php
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Horde\Rpc\JsonRpc\Dispatch\MathApiProvider;
use Horde\Rpc\JsonRpc\JsonRpcHandler;
use Horde\Rpc\Mcp\McpServer;
use Horde\Rpc\Mcp\Protocol\ServerInfo;
use Horde\Rpc\Soap\SoapHandler;

$math = MathApiProvider::create();
$responseFactory = new ResponseFactory();
$streamFactory = new StreamFactory();

// JSON-RPC
$jsonRpc = new JsonRpcHandler(
    $math, $math,
    $responseFactory, $streamFactory, $eventDispatcher,
);

// MCP
$mcp = new McpServer(
    new ServerInfo('horde-math', '1.0.0'),
    $math, $math,
    $responseFactory, $streamFactory,
);

// SOAP
$soap = new SoapHandler(
    $math, $math,
    $responseFactory, $streamFactory,
);

// Stack: each protocol detects and claims, or passes through
$app->pipe($soap);                  // text/xml → SOAP
$app->pipe($mcp->getHandler());     // /mcp + JSON → MCP
$app->pipe($jsonRpc->getHandler()); // /rpc/jsonrpc + JSON → JSON-RPC
```

## Custom Configuration

```php
$handler = new SoapHandler(
    provider: $provider,
    invoker: $provider,
    responseFactory: new ResponseFactory(),
    streamFactory: new StreamFactory(),
    path: '/api/soap',                       // custom path (default: /rpc/soap)
    serviceUri: 'urn:my-app-soap-service',   // SOAP service URI (default: urn:horde-rpc-soap)
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

Three protocols, one dispatch layer. Write your provider once, serve it
over JSON-RPC, MCP, and SOAP.
