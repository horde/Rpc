<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\Soap;

use Horde\Http\ResponseFactory;
use Horde\Http\ServerRequest;
use Horde\Http\StreamFactory;
use Horde\Http\Uri;
use Horde\Rpc\Dispatch\CallableMapProvider;
use Horde\Rpc\Soap\SoapHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(SoapHandler::class)]
class SoapHandlerTest extends TestCase
{
    private ResponseFactory $responseFactory;
    private StreamFactory $streamFactory;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
        $this->streamFactory = new StreamFactory();
    }

    private function makeHandler(array $methods = []): SoapHandler
    {
        $provider = new CallableMapProvider($methods);

        return new SoapHandler(
            $provider,
            $provider,
            $this->responseFactory,
            $this->streamFactory,
        );
    }

    private function makeNextHandler(): RequestHandlerInterface
    {
        $responseFactory = new ResponseFactory();

        return new class ($responseFactory) implements RequestHandlerInterface {
            public function __construct(
                private readonly \Psr\Http\Message\ResponseFactoryInterface $rf,
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->rf->createResponse(404);
            }
        };
    }

    private function makeSoapRequest(
        string $method,
        array $params = [],
        string $path = '/rpc/soap',
        string $contentType = 'text/xml',
    ): ServerRequest {
        $paramXml = '';
        foreach ($params as $name => $value) {
            $paramXml .= sprintf('<%s>%s</%s>', $name, htmlspecialchars((string) $value, ENT_XML1), $name);
        }

        $soapBody = sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"'
            . ' xmlns:ns1="urn:horde-rpc-soap">'
            . '<SOAP-ENV:Body>'
            . '<ns1:%s>%s</ns1:%s>'
            . '</SOAP-ENV:Body>'
            . '</SOAP-ENV:Envelope>',
            $method,
            $paramXml,
            $method,
        );

        $body = $this->streamFactory->createStream($soapBody);

        return new ServerRequest(
            method: 'POST',
            uri: new Uri($path),
            body: $body,
            headers: [
                'Content-Type' => $contentType,
                'SOAPAction' => '"urn:horde-rpc-soap#' . $method . '"',
            ],
        );
    }

    // --- Middleware detection tests ---

    public function testProcessTextXmlMatchesSoap(): void
    {
        $handler = $this->makeHandler(['ping' => fn() => 'pong']);
        $request = $this->makeSoapRequest('ping');

        $response = $handler->process($request, $this->makeNextHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/xml', $response->getHeaderLine('Content-Type'));
    }

    public function testProcessSoapXmlContentTypeMatches(): void
    {
        $handler = $this->makeHandler(['ping' => fn() => 'pong']);
        $request = $this->makeSoapRequest('ping', contentType: 'application/soap+xml; charset=utf-8');

        $response = $handler->process($request, $this->makeNextHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testProcessGetPassesToNext(): void
    {
        $handler = $this->makeHandler();
        $request = new ServerRequest(
            method: 'GET',
            uri: new Uri('/rpc/soap'),
            headers: ['Content-Type' => 'text/xml'],
        );

        $response = $handler->process($request, $this->makeNextHandler());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testProcessWrongPathPassesToNext(): void
    {
        $handler = $this->makeHandler();
        $request = new ServerRequest(
            method: 'POST',
            uri: new Uri('/other/path'),
            headers: ['Content-Type' => 'text/xml'],
        );

        $response = $handler->process($request, $this->makeNextHandler());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testProcessJsonContentTypePassesToNext(): void
    {
        $handler = $this->makeHandler();
        $request = new ServerRequest(
            method: 'POST',
            uri: new Uri('/rpc/soap'),
            headers: ['Content-Type' => 'application/json'],
        );

        $response = $handler->process($request, $this->makeNextHandler());

        $this->assertSame(404, $response->getStatusCode());
    }

    // --- Handler (handle()) tests ---

    public function testHandleEmptyBody(): void
    {
        $handler = $this->makeHandler();
        $body = $this->streamFactory->createStream('');
        $request = new ServerRequest(
            method: 'POST',
            uri: new Uri('/rpc/soap'),
            body: $body,
            headers: ['Content-Type' => 'text/xml'],
        );

        $response = $handler->handle($request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('Empty SOAP request body', (string) $response->getBody());
    }

    // --- SOAP invocation tests (require ext-soap) ---

    #[RequiresPhpExtension('soap')]
    public function testSoapMethodInvocation(): void
    {
        $handler = $this->makeHandler([
            'ping' => fn() => 'pong',
        ]);
        $request = $this->makeSoapRequest('ping');

        $response = $handler->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $responseBody = (string) $response->getBody();
        $this->assertStringContainsString('pong', $responseBody);
    }

    #[RequiresPhpExtension('soap')]
    public function testSoapMethodNotFound(): void
    {
        $handler = $this->makeHandler([]);
        $request = $this->makeSoapRequest('nonexistent');

        $response = $handler->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $responseBody = (string) $response->getBody();
        // SoapServer returns a SOAP Fault
        $this->assertStringContainsString('Fault', $responseBody);
        $this->assertStringContainsString('not defined', $responseBody);
    }

    #[RequiresPhpExtension('soap')]
    public function testSoapMethodWithParameters(): void
    {
        $handler = $this->makeHandler([
            'math.add' => fn(float $a, float $b) => $a + $b,
        ]);

        // SOAP with positional params
        $soapBody = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"'
            . ' xmlns:ns1="urn:horde-rpc-soap"'
            . ' xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<SOAP-ENV:Body>'
            . '<ns1:math.add>'
            . '<param0 xsi:type="xsd:float">3</param0>'
            . '<param1 xsi:type="xsd:float">4</param1>'
            . '</ns1:math.add>'
            . '</SOAP-ENV:Body>'
            . '</SOAP-ENV:Envelope>';

        $body = $this->streamFactory->createStream($soapBody);
        $request = new ServerRequest(
            method: 'POST',
            uri: new Uri('/rpc/soap'),
            body: $body,
            headers: ['Content-Type' => 'text/xml'],
        );

        $response = $handler->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('7', (string) $response->getBody());
    }

    // --- Custom path test ---

    public function testCustomPath(): void
    {
        $provider = new CallableMapProvider(['ping' => fn() => 'pong']);
        $handler = new SoapHandler(
            $provider,
            $provider,
            $this->responseFactory,
            $this->streamFactory,
            path: '/api/soap',
        );
        $request = $this->makeSoapRequest('ping', path: '/api/soap');

        $response = $handler->process($request, $this->makeNextHandler());

        $this->assertSame(200, $response->getStatusCode());
    }
}
