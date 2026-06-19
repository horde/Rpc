<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Soap;

use Horde\Http\Client\Curl;
use Horde\Http\Client\Options;
use Horde\Http\RequestFactory;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Horde\Rpc\Soap\Exception\SoapException;
use Horde\Rpc\Soap\Transport\Psr18SoapTransport;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SoapFault;

/**
 * Convenience facade for SOAP client calls.
 *
 * Supports both WSDL and WSDL-less ("non-WSDL") mode through a single
 * constructor; the WSDL argument is optional. All wire I/O is routed
 * through a PSR-18 HTTP client, defaulting to {@see Curl} when no
 * client is provided so the SOAP transport stays uniform with the
 * rest of the modern Horde stack.
 *
 * Native ext-soap \SoapFault exceptions are caught and re-thrown as
 * {@see SoapException} so callers only ever need to handle one type.
 *
 * Examples (use named arguments throughout — the constructor accepts
 * many optional knobs and positional ordering is intentionally not part
 * of the public contract beyond `$endpoint`):
 *
 *     // WSDL mode
 *     $client = new SoapClient(
 *         endpoint: 'https://api.example.com/soap',
 *         wsdl: 'https://api.example.com/soap?wsdl',
 *     );
 *     $user = $client->call('getUser', [42]);
 *
 *     // WSDL-less mode
 *     $client = new SoapClient(
 *         endpoint: 'https://api.example.com/soap',
 *         uri: 'urn:example-service',
 *     );
 *     $user = $client->call('getUser', [42]);
 *
 *     // SOAP 1.2 with basic auth and a custom PSR-18 client
 *     $client = new SoapClient(
 *         endpoint: 'https://api.example.com/soap',
 *         wsdl: 'https://api.example.com/soap?wsdl',
 *         version: SoapVersion::V1_2,
 *         login: 'alice',
 *         password: 'secret',
 *         httpClient: $guzzleClient,
 *     );
 */
final class SoapClient
{
    private readonly Psr18SoapTransport $transport;

    /**
     * @param string $endpoint The service endpoint URL. Used as the SOAP `location`
     *        and as the target for every HTTP request, overriding any address
     *        embedded in the WSDL.
     * @param string|null $wsdl WSDL URI, or null for WSDL-less mode. When null,
     *        `$uri` must be supplied.
     * @param string|null $uri SOAP namespace / service URI. Required in WSDL-less
     *        mode, ignored when a WSDL is supplied.
     * @param SoapVersion $version SOAP protocol version.
     * @param string|null $userAgent Override the User-Agent header on outbound
     *        HTTP requests.
     * @param int|null $connectionTimeout Connection timeout in seconds (passed
     *        to ext-soap as `connection_timeout`). Applies to WSDL fetching;
     *        per-request HTTP timeouts come from the PSR-18 client.
     * @param string|null $login HTTP basic-auth username.
     * @param string|null $password HTTP basic-auth password.
     * @param ClientInterface|null $httpClient PSR-18 client, defaults to
     *        {@see Curl}.
     * @param RequestFactoryInterface|null $requestFactory PSR-17 request factory,
     *        defaults to {@see RequestFactory}.
     * @param StreamFactoryInterface|null $streamFactory PSR-17 stream factory,
     *        defaults to {@see StreamFactory}.
     *
     * @throws InvalidArgumentException When `$wsdl` is null and `$uri` is also null.
     */
    public function __construct(
        string $endpoint,
        ?string $wsdl = null,
        ?string $uri = null,
        SoapVersion $version = SoapVersion::V1_1,
        ?string $userAgent = null,
        ?int $connectionTimeout = null,
        ?string $login = null,
        ?string $password = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        if ($wsdl === null && ($uri === null || $uri === '')) {
            throw new InvalidArgumentException(
                'SoapClient requires either a WSDL URI or, in WSDL-less mode, a service URI.',
            );
        }

        $streamFactory ??= new StreamFactory();
        $requestFactory ??= new RequestFactory();

        if ($httpClient === null) {
            $responseFactory = new ResponseFactory();
            $httpClient = new Curl($responseFactory, $streamFactory, new Options());
        }

        $options = [
            'location' => $endpoint,
            'soap_version' => $version->toExtSoapConstant(),
            'exceptions' => true,
            // trace lets callers inspect the last request/response via
            // ext-soap's __getLast*() helpers when debugging.
            'trace' => true,
        ];

        if ($wsdl === null) {
            $options['uri'] = $uri;
        }

        if ($connectionTimeout !== null) {
            $options['connection_timeout'] = $connectionTimeout;
        }

        if ($login !== null) {
            $options['login'] = $login;
        }

        if ($password !== null) {
            $options['password'] = $password;
        }

        $this->transport = new Psr18SoapTransport(
            wsdl: $wsdl,
            options: $options,
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
            userAgent: $userAgent ?? 'Horde SOAP Client',
        );
    }

    /**
     * Invoke a SOAP method on the remote service.
     *
     * Parameters are positional and passed straight through to ext-soap,
     * which marshals them according to the WSDL (WSDL mode) or wraps them
     * as a generic request (WSDL-less mode). For named-parameter SOAP
     * methods, pass a single associative array.
     *
     * @param string $method The SOAP operation name.
     * @param array<int|string, mixed> $params Arguments for the operation.
     *
     * @return mixed The unmarshaled return value.
     *
     * @throws SoapException On any SOAP fault, including HTTP transport faults
     *         re-raised by {@see Psr18SoapTransport}.
     */
    public function call(string $method, array $params = []): mixed
    {
        try {
            return $this->transport->__soapCall($method, $params);
        } catch (SoapFault $e) {
            throw SoapException::fromSoapFault($e);
        }
    }

    /**
     * Return the raw XML of the last request sent, if any.
     *
     * Useful for debugging. Requires that the underlying \SoapClient was
     * built with the `trace` option (which this facade always sets).
     */
    public function getLastRequest(): ?string
    {
        $xml = $this->transport->__getLastRequest();

        return $xml !== null && $xml !== '' ? $xml : null;
    }

    /**
     * Return the raw XML of the last response received, if any.
     */
    public function getLastResponse(): ?string
    {
        $xml = $this->transport->__getLastResponse();

        return $xml !== null && $xml !== '' ? $xml : null;
    }
}
