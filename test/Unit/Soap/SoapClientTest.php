<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\Soap;

use Horde\Http\Response;
use Horde\Http\StreamFactory;
use Horde\Rpc\Soap\Exception\SoapException;
use Horde\Rpc\Soap\SoapClient;
use Horde\Rpc\Soap\SoapVersion;
use Horde\Rpc\Soap\Transport\Psr18SoapTransport;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

#[CoversClass(SoapClient::class)]
#[CoversClass(Psr18SoapTransport::class)]
#[CoversClass(SoapVersion::class)]
class SoapClientTest extends TestCase
{
    private StreamFactory $streamFactory;

    protected function setUp(): void
    {
        $this->streamFactory = new StreamFactory();
    }

    public function testConstructorRejectsBothWsdlAndUriMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SoapClient requires either a WSDL URI');

        new SoapClient(
            endpoint: 'http://example.com/soap',
            httpClient: $this->makeRecordingClient(''),
        );
    }

    public function testConstructorRejectsEmptyUri(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SoapClient(
            endpoint: 'http://example.com/soap',
            uri: '',
            httpClient: $this->makeRecordingClient(''),
        );
    }

    #[RequiresPhpExtension('soap')]
    public function testWsdlLessCallSendsSoapRequestAndUnmarshalsResponse(): void
    {
        $http = $this->makeRecordingClient($this->buildSoapResponse(
            method: 'getUser',
            namespace: 'urn:horde-test',
            innerXml: '<return xsi:type="xsd:string">alice</return>',
        ));

        $client = new SoapClient(
            endpoint: 'http://example.com/soap',
            uri: 'urn:horde-test',
            httpClient: $http,
        );

        $result = $client->call('getUser', [42]);

        $this->assertSame('alice', $result);

        $sent = $http->lastRequest;
        $this->assertNotNull($sent);
        $this->assertSame('POST', $sent->getMethod());
        $this->assertSame('http://example.com/soap', (string) $sent->getUri());
        $this->assertStringContainsString('text/xml', $sent->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('Horde SOAP Client', $sent->getHeaderLine('User-Agent'));
        // SOAP 1.1 sets a SOAPAction header
        $this->assertNotSame('', $sent->getHeaderLine('SOAPAction'));
        // The body should be a SOAP envelope mentioning the method
        $this->assertStringContainsString('getUser', (string) $sent->getBody());
    }

    #[RequiresPhpExtension('soap')]
    public function testSoap12UsesApplicationSoapXmlContentType(): void
    {
        $http = $this->makeRecordingClient($this->buildSoapResponse(
            method: 'getUser',
            namespace: 'urn:horde-test',
            innerXml: '<return xsi:type="xsd:string">bob</return>',
            soap12: true,
        ));

        $client = new SoapClient(
            endpoint: 'http://example.com/soap',
            uri: 'urn:horde-test',
            version: SoapVersion::V1_2,
            httpClient: $http,
        );

        $client->call('getUser', [1]);

        $sent = $http->lastRequest;
        $this->assertNotNull($sent);
        $this->assertStringContainsString('application/soap+xml', $sent->getHeaderLine('Content-Type'));
        // SOAP 1.2 does not use the SOAPAction header.
        $this->assertSame('', $sent->getHeaderLine('SOAPAction'));
    }

    #[RequiresPhpExtension('soap')]
    public function testCustomUserAgentIsForwarded(): void
    {
        $http = $this->makeRecordingClient($this->buildSoapResponse(
            method: 'ping',
            namespace: 'urn:horde-test',
            innerXml: '<return xsi:type="xsd:string">pong</return>',
        ));

        $client = new SoapClient(
            endpoint: 'http://example.com/soap',
            uri: 'urn:horde-test',
            userAgent: 'AcmeCorp/2.0',
            httpClient: $http,
        );

        $client->call('ping');

        $sent = $http->lastRequest;
        $this->assertNotNull($sent);
        $this->assertSame('AcmeCorp/2.0', $sent->getHeaderLine('User-Agent'));
    }

    #[RequiresPhpExtension('soap')]
    public function testSoapFaultIsConvertedToSoapException(): void
    {
        $http = $this->makeRecordingClient($this->buildSoapFaultResponse(
            faultCode: 'Client',
            faultString: 'bad input',
        ));

        $client = new SoapClient(
            endpoint: 'http://example.com/soap',
            uri: 'urn:horde-test',
            httpClient: $http,
        );

        try {
            $client->call('explode');
            $this->fail('Expected SoapException');
        } catch (SoapException $e) {
            $this->assertStringContainsString('bad input', $e->getMessage());
            $this->assertNotSame('', $e->getFaultCode());
        }
    }

    #[RequiresPhpExtension('soap')]
    public function testHttpTransportFailureSurfacesAsSoapException(): void
    {
        $http = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new class ('network down') extends RuntimeException implements ClientExceptionInterface {};
            }
        };

        $client = new SoapClient(
            endpoint: 'http://example.com/soap',
            uri: 'urn:horde-test',
            httpClient: $http,
        );

        $this->expectException(SoapException::class);
        $this->expectExceptionMessage('SOAP HTTP transport failed');

        $client->call('ping');
    }

    #[RequiresPhpExtension('soap')]
    public function testGetLastRequestAndResponseAreExposed(): void
    {
        $responseBody = $this->buildSoapResponse(
            method: 'ping',
            namespace: 'urn:horde-test',
            innerXml: '<return xsi:type="xsd:string">pong</return>',
        );
        $http = $this->makeRecordingClient($responseBody);

        $client = new SoapClient(
            endpoint: 'http://example.com/soap',
            uri: 'urn:horde-test',
            httpClient: $http,
        );

        $client->call('ping');

        $this->assertNotNull($client->getLastRequest());
        $this->assertStringContainsString('ping', $client->getLastRequest() ?? '');
        $this->assertSame($responseBody, $client->getLastResponse());
    }

    /**
     * Build a recording PSR-18 client that returns a fixed response body.
     */
    private function makeRecordingClient(string $responseBody, int $statusCode = 200): object
    {
        $streamFactory = $this->streamFactory;

        return new class ($responseBody, $statusCode, $streamFactory) implements ClientInterface {
            public ?RequestInterface $lastRequest = null;

            public function __construct(
                private readonly string $responseBody,
                private readonly int $statusCode,
                private readonly StreamFactory $sf,
            ) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->lastRequest = $request;
                $body = $this->sf->createStream($this->responseBody);

                return new Response($this->statusCode, body: $body);
            }
        };
    }

    private function buildSoapResponse(
        string $method,
        string $namespace,
        string $innerXml,
        bool $soap12 = false,
    ): string {
        $envelopeNs = $soap12
            ? 'http://www.w3.org/2003/05/soap-envelope'
            : 'http://schemas.xmlsoap.org/soap/envelope/';

        return sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<SOAP-ENV:Envelope xmlns:SOAP-ENV="%s"'
            . ' xmlns:ns1="%s"'
            . ' xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<SOAP-ENV:Body>'
            . '<ns1:%sResponse>%s</ns1:%sResponse>'
            . '</SOAP-ENV:Body>'
            . '</SOAP-ENV:Envelope>',
            $envelopeNs,
            $namespace,
            $method,
            $innerXml,
            $method,
        );
    }

    private function buildSoapFaultResponse(string $faultCode, string $faultString): string
    {
        return sprintf(
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
    }
}
