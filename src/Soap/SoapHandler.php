<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Soap;

use Horde\Rpc\Dispatch\ApiProviderInterface;
use Horde\Rpc\Dispatch\MethodInvokerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SoapServer;
use Throwable;

/**
 * PSR-15 handler and middleware for SOAP over HTTP.
 *
 * Implements both RequestHandlerInterface (direct routing) and
 * MiddlewareInterface (protocol detection in a middleware stack).
 *
 * Uses ext-soap in WSDL-less mode. If ext-soap is not loaded, returns
 * a SOAP Fault with HTTP 501 — other protocols in the stack are not
 * affected.
 *
 * As middleware: detects SOAP requests by method, path, and content
 * type (text/xml, application/soap+xml), handles matching requests
 * or passes through.
 *
 * As handler: processes the request as SOAP unconditionally.
 */
final class SoapHandler implements RequestHandlerInterface, MiddlewareInterface
{
    public function __construct(
        private readonly ApiProviderInterface $provider,
        private readonly MethodInvokerInterface $invoker,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $path = '/rpc/soap',
        private readonly string $serviceUri = 'urn:horde-rpc-soap',
    ) {}

    /**
     * PSR-15 MiddlewareInterface: detect SOAP request or pass through.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $next,
    ): ResponseInterface {
        if ($this->matches($request)) {
            return $this->handleSoap($request);
        }

        return $next->handle($request);
    }

    /**
     * PSR-15 RequestHandlerInterface: handle request as SOAP.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handleSoap($request);
    }

    /**
     * Check whether this request looks like a SOAP request.
     *
     * Matches: POST + configured path + SOAP content type.
     * SOAP 1.1 uses text/xml, SOAP 1.2 uses application/soap+xml.
     * SOAPAction header is optional (present in 1.1, may be absent in 1.2).
     */
    private function matches(ServerRequestInterface $request): bool
    {
        if ($request->getMethod() !== 'POST') {
            return false;
        }

        if ($request->getUri()->getPath() !== $this->path) {
            return false;
        }

        return $this->isSoapContentType($request->getHeaderLine('Content-Type'));
    }

    private function isSoapContentType(string $contentType): bool
    {
        return str_contains($contentType, 'text/xml')
            || str_contains($contentType, 'application/soap+xml');
    }

    private function handleSoap(ServerRequestInterface $request): ResponseInterface
    {
        if (!extension_loaded('soap')) {
            return $this->soapFaultResponse(
                'Server',
                'SOAP support requires the PHP soap extension',
                501,
            );
        }

        $soapBody = (string) $request->getBody();
        if ($soapBody === '') {
            return $this->soapFaultResponse('Client', 'Empty SOAP request body');
        }

        $callHandler = new SoapCallHandler($this->provider, $this->invoker);

        $server = new SoapServer(null, ['uri' => $this->serviceUri]);
        $server->setObject($callHandler);

        ob_start();
        try {
            $server->handle($soapBody);
        } catch (Throwable) {
            ob_end_clean();
            return $this->soapFaultResponse('Server', 'Internal error');
        }
        $responseXml = ob_get_clean();

        if ($responseXml === false || $responseXml === '') {
            return $this->soapFaultResponse('Server', 'Internal error');
        }

        $body = $this->streamFactory->createStream($responseXml);

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/xml; charset=utf-8')
            ->withBody($body);
    }

    private function soapFaultResponse(
        string $faultCode,
        string $faultString,
        int $httpStatus = 500,
    ): ResponseInterface {
        $xml = sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<SOAP-ENV:Body>'
            . '<SOAP-ENV:Fault>'
            . '<faultcode>SOAP-ENV:%s</faultcode>'
            . '<faultstring>%s</faultstring>'
            . '</SOAP-ENV:Fault>'
            . '</SOAP-ENV:Body>'
            . '</SOAP-ENV:Envelope>',
            htmlspecialchars($faultCode, ENT_XML1, 'UTF-8'),
            htmlspecialchars($faultString, ENT_XML1, 'UTF-8'),
        );

        $body = $this->streamFactory->createStream($xml);

        return $this->responseFactory->createResponse($httpStatus)
            ->withHeader('Content-Type', 'text/xml; charset=utf-8')
            ->withBody($body);
    }
}
