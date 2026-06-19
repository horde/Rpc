<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Soap\Transport;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SoapClient as ExtSoapClient;
use SoapFault;

/**
 * \SoapClient subclass that routes wire I/O through a PSR-18 client.
 *
 * ext-soap parses WSDLs, marshals types, builds and reads SOAP envelopes,
 * and handles fault generation. The only thing it does poorly is HTTP:
 * it has its own minimal socket-based stack, ignores PSR-18 entirely, and
 * cannot share configuration with the rest of the Horde HTTP layer.
 *
 * Overriding {@see ExtSoapClient::__doRequest()} is the supported
 * extension point: ext-soap invokes it with the serialized envelope, the
 * location URL, the SOAPAction header, and the SOAP version, and expects
 * the raw response body back. We forward the envelope through PSR-18.
 *
 * Faults during transport (HTTP-level failures) are surfaced as
 * \SoapFault so ext-soap's normal error path applies and the caller
 * gets a uniform exception type at the {@see \Horde\Rpc\Soap\SoapClient}
 * facade.
 *
 * @internal Used by SoapClient; not part of the public API.
 */
final class Psr18SoapTransport extends ExtSoapClient
{
    /**
     * @param string|null $wsdl WSDL URI, or null for WSDL-less ("non-WSDL") mode.
     * @param array<string, mixed> $options ext-soap options; must contain at least
     *        'location' (and 'uri' in non-WSDL mode). Built by {@see \Horde\Rpc\Soap\SoapClient}.
     */
    public function __construct(
        ?string $wsdl,
        array $options,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $userAgent = 'Horde SOAP Client',
    ) {
        parent::__construct($wsdl, $options);
    }

    /**
     * ext-soap entry point for outbound HTTP. Returns the raw response body.
     *
     * @param string $request Serialized SOAP envelope.
     * @param string $location Target URL (from the WSDL, the 'location' option, or per-call override).
     * @param string $action SOAPAction header value.
     * @param int $version SOAP_1_1 or SOAP_1_2.
     * @param int $oneWay Non-zero when ext-soap considers the call one-way (no response expected).
     *
     * @return string The raw response body, or empty string for one-way calls.
     *
     * @throws SoapFault On any HTTP-level failure. Wrapping the PSR-18 exception
     *                   in a \SoapFault keeps ext-soap's own error handling
     *                   uniform; the caller-facing facade re-wraps as SoapException.
     */
    public function __doRequest(
        string $request,
        string $location,
        string $action,
        int $version,
        $oneWay = 0,
    ): string {
        $contentType = $version === SOAP_1_2
            ? 'application/soap+xml; charset=utf-8'
            : 'text/xml; charset=utf-8';

        $httpRequest = $this->requestFactory->createRequest('POST', $location)
            ->withHeader('Content-Type', $contentType)
            ->withHeader('User-Agent', $this->userAgent)
            ->withBody($this->streamFactory->createStream($request));

        // SOAP 1.1 puts the action in a separate header; SOAP 1.2 carries it
        // as a Content-Type parameter, but ext-soap still passes the action
        // string here, so we set it as a header for 1.1 only.
        if ($version === SOAP_1_1 && $action !== '') {
            $httpRequest = $httpRequest->withHeader('SOAPAction', $action);
        }

        try {
            $httpResponse = $this->httpClient->sendRequest($httpRequest);
        } catch (ClientExceptionInterface $e) {
            throw new SoapFault('HTTP', 'SOAP HTTP transport failed: ' . $e->getMessage());
        }

        if ($oneWay) {
            return '';
        }

        return (string) $httpResponse->getBody();
    }
}
