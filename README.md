# Horde RPC Library

Various RPC-like adapters into Horde's API system

- SOAP
- XMLRPC (relies on xmlrpc language extension, probably not usable in PHP 8.x until we re-implement it in pure PHP)
- json-rpc
- PHPGroupware flavoured XMLRPC (deprecated, probably useless by now)
- syncml (deprecated, delegates to horde/syncml)
- activesync (delegates to horde/activesync)
- webdav, caldav, carddav (delegates to horde/dav)