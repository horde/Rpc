# Horde RPC Library

RPC adapters for Horde's API system. Modern implementations share a
dispatch layer (`ApiProviderInterface`, `MethodInvokerInterface`) across
protocols — write a provider once, serve it over JSON-RPC, MCP, and SOAP.

## Protocols

### JSON-RPC

| | |
|-|-|
| Modern | [`src/JsonRpc/`](src/JsonRpc/) — PSR-7/15/18, JSON-RPC [1.1](https://www.jsonrpc.org/historical/json-rpc-1-1-alt.html) and [2.0](https://www.jsonrpc.org/specification) |
| Legacy | [`lib/Horde/Rpc/Jsonrpc.php`](lib/Horde/Rpc/Jsonrpc.php) |
| Docs | [`doc/JSONRPC-USAGE.md`](doc/JSONRPC-USAGE.md) |

### MCP (Model Context Protocol)

| | |
|-|-|
| Modern | [`src/Mcp/`](src/Mcp/) — [MCP spec](https://modelcontextprotocol.io/specification/2025-11-25), Streamable HTTP transport |
| Docs | [`doc/MCP-USAGE.md`](doc/MCP-USAGE.md) |

### SOAP

| | |
|-|-|
| Modern | [`src/Soap/`](src/Soap/) — PSR-15, ext-soap (optional) in WSDL-less mode |
| Legacy | [`lib/Horde/Rpc/Soap.php`](lib/Horde/Rpc/Soap.php) |
| Docs | [`doc/SOAP-USAGE.md`](doc/SOAP-USAGE.md) |

### XML-RPC

| | |
|-|-|
| Legacy | [`lib/Horde/Rpc/Xmlrpc.php`](lib/Horde/Rpc/Xmlrpc.php) — requires ext-xmlrpc, will probably not work in most modern PHP installations [XML-RPC spec](http://xmlrpc.com/spec.md) |

### Delegation Protocols

These delegate to other Horde packages:

- **ActiveSync** — [`lib/Horde/Rpc/ActiveSync.php`](lib/Horde/Rpc/ActiveSync.php) → `horde/activesync`
- **WebDAV / CalDAV / CardDAV** — [`lib/Horde/Rpc/Webdav.php`](lib/Horde/Rpc/Webdav.php) → `horde/dav`
- **SyncML** — [`lib/Horde/Rpc/Syncml.php`](lib/Horde/Rpc/Syncml.php) → `horde/syncml` (deprecated)
- **PHPGroupware XML-RPC** — [`lib/Horde/Rpc/Phpgw.php`](lib/Horde/Rpc/Phpgw.php) (deprecated)

## Shared Dispatch Layer

```
JsonRpcHandler ──┐
McpServer ───────┤── ApiProviderInterface / MethodInvokerInterface
SoapHandler ─────┘
```
### Providers

[`CallableMapProvider`](src/JsonRpc/Dispatch/CallableMapProvider.php) - a generic map of callables to API names
[`MathApiProvider`](src/JsonRpc/Dispatch/MathApiProvider.php) - an educational example API provider
[`HordeRegistryApiProvider`](src/JsonRpc/Dispatch/HordeRegistryApiProvider.php) - Integrates the Horde Registry Inter-App API

All modern handlers implement both `RequestHandlerInterface` and
`MiddlewareInterface` for flexible middleware stacking.
