<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\Protocol;

use Horde\Rpc\JsonRpc\Exception\InvalidRequestException;
use Horde\Rpc\JsonRpc\Exception\ParseException;
use Horde\Rpc\JsonRpc\Protocol\Batch;
use Horde\Rpc\JsonRpc\Protocol\Codec;
use Horde\Rpc\JsonRpc\Protocol\Error;
use Horde\Rpc\JsonRpc\Protocol\ErrorCode;
use Horde\Rpc\JsonRpc\Protocol\Request;
use Horde\Rpc\JsonRpc\Protocol\Response;
use Horde\Rpc\JsonRpc\Protocol\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Codec::class)]
class CodecTest extends TestCase
{
    private Codec $codec;

    protected function setUp(): void
    {
        $this->codec = new Codec();
    }

    // --- decode: valid requests ---

    public function testDecodeV20Request(): void
    {
        $json = '{"jsonrpc":"2.0","method":"math.add","params":[1,2],"id":1}';
        $result = $this->codec->decode($json);

        $this->assertInstanceOf(Request::class, $result);
        $this->assertSame(Version::V2_0, $result->version);
        $this->assertSame('math.add', $result->method);
        $this->assertSame([1, 2], $result->params);
        $this->assertSame(1, $result->id);
        $this->assertFalse($result->isNotification());
    }

    public function testDecodeV20RequestWithNamedParams(): void
    {
        $json = '{"jsonrpc":"2.0","method":"user.create","params":{"name":"John","age":30},"id":"abc"}';
        $result = $this->codec->decode($json);

        $this->assertInstanceOf(Request::class, $result);
        $this->assertSame(['name' => 'John', 'age' => 30], $result->params);
        $this->assertSame('abc', $result->id);
    }

    public function testDecodeV20Notification(): void
    {
        $json = '{"jsonrpc":"2.0","method":"notify"}';
        $result = $this->codec->decode($json);

        $this->assertInstanceOf(Request::class, $result);
        $this->assertTrue($result->isNotification());
    }

    public function testDecodeV11Request(): void
    {
        $json = '{"version":"1.1","method":"test","params":[1],"id":1}';
        $result = $this->codec->decode($json);

        $this->assertInstanceOf(Request::class, $result);
        $this->assertSame(Version::V1_1, $result->version);
    }

    public function testDecodeDefaultsToV20(): void
    {
        $json = '{"method":"test","id":1}';
        $result = $this->codec->decode($json);

        $this->assertInstanceOf(Request::class, $result);
        $this->assertSame(Version::V2_0, $result->version);
    }

    public function testDecodeMissingParamsDefaultsToEmptyArray(): void
    {
        $json = '{"jsonrpc":"2.0","method":"test","id":1}';
        $result = $this->codec->decode($json);

        $this->assertSame([], $result->params);
    }

    public function testDecodeStringId(): void
    {
        $json = '{"jsonrpc":"2.0","method":"test","params":[],"id":"request-42"}';
        $result = $this->codec->decode($json);

        $this->assertSame('request-42', $result->id);
    }

    // --- decode: error cases ---

    public function testDecodeInvalidJsonThrowsParseException(): void
    {
        $this->expectException(ParseException::class);
        $this->codec->decode('{invalid json');
    }

    public function testDecodeScalarThrowsInvalidRequest(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->codec->decode('42');
    }

    public function testDecodeStringThrowsInvalidRequest(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->codec->decode('"hello"');
    }

    public function testDecodeMissingMethodThrowsInvalidRequest(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->codec->decode('{"jsonrpc":"2.0","id":1}');
    }

    public function testDecodeNonStringMethodThrowsInvalidRequest(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->codec->decode('{"jsonrpc":"2.0","method":42,"id":1}');
    }

    public function testDecodeNonArrayNonObjectParamsThrowsInvalidRequest(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->codec->decode('{"jsonrpc":"2.0","method":"test","params":"bad","id":1}');
    }

    // --- decode: batch ---

    public function testDecodeBatch(): void
    {
        $json = '[{"jsonrpc":"2.0","method":"a","id":1},{"jsonrpc":"2.0","method":"b","id":2}]';
        $result = $this->codec->decode($json);

        $this->assertInstanceOf(Batch::class, $result);
        $this->assertCount(2, $result);

        $items = $result->requests;
        $this->assertInstanceOf(Request::class, $items[0]);
        $this->assertSame('a', $items[0]->method);
        $this->assertInstanceOf(Request::class, $items[1]);
        $this->assertSame('b', $items[1]->method);
    }

    public function testDecodeEmptyBatchThrowsInvalidRequest(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->codec->decode('[]');
    }

    public function testDecodeBatchWithInvalidEntry(): void
    {
        $json = '[{"jsonrpc":"2.0","method":"a","id":1},{"jsonrpc":"2.0","id":2}]';
        $result = $this->codec->decode($json);

        $this->assertInstanceOf(Batch::class, $result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Request::class, $result->requests[0]);
        $this->assertInstanceOf(Error::class, $result->requests[1]);
    }

    public function testDecodeBatchWithNonObjectEntry(): void
    {
        $json = '[{"jsonrpc":"2.0","method":"a","id":1},42]';
        $result = $this->codec->decode($json);

        $this->assertInstanceOf(Batch::class, $result);
        $this->assertInstanceOf(Error::class, $result->requests[1]);
    }

    public function testDecodeSingleElementBatch(): void
    {
        $json = '[{"jsonrpc":"2.0","method":"a","id":1}]';
        $result = $this->codec->decode($json);

        $this->assertInstanceOf(Batch::class, $result);
        $this->assertCount(1, $result);
    }

    // --- encodeResponse ---

    public function testEncodeResponseV20(): void
    {
        $response = new Response(Version::V2_0, 42, 1);
        $json = $this->codec->encodeResponse($response);
        $decoded = json_decode($json, true);

        $this->assertSame('2.0', $decoded['jsonrpc']);
        $this->assertSame(42, $decoded['result']);
        $this->assertSame(1, $decoded['id']);
        $this->assertArrayNotHasKey('version', $decoded);
    }

    public function testEncodeResponseV11(): void
    {
        $response = new Response(Version::V1_1, 'hello', 1);
        $json = $this->codec->encodeResponse($response);
        $decoded = json_decode($json, true);

        $this->assertSame('1.1', $decoded['version']);
        $this->assertSame('hello', $decoded['result']);
        $this->assertArrayNotHasKey('jsonrpc', $decoded);
    }

    public function testEncodeResponseNullResult(): void
    {
        $response = new Response(Version::V2_0, null, 1);
        $json = $this->codec->encodeResponse($response);
        $decoded = json_decode($json, true);

        $this->assertNull($decoded['result']);
    }

    // --- encodeError ---

    public function testEncodeErrorV20(): void
    {
        $error = new Error(Version::V2_0, ErrorCode::MethodNotFound, 'Not found', null, 1);
        $json = $this->codec->encodeError($error);
        $decoded = json_decode($json, true);

        $this->assertSame('2.0', $decoded['jsonrpc']);
        $this->assertSame(-32601, $decoded['error']['code']);
        $this->assertSame('Not found', $decoded['error']['message']);
        $this->assertArrayNotHasKey('data', $decoded['error']);
        $this->assertSame(1, $decoded['id']);
    }

    public function testEncodeErrorV20WithData(): void
    {
        $error = new Error(Version::V2_0, ErrorCode::InternalError, 'fail', ['detail' => 'x'], 1);
        $json = $this->codec->encodeError($error);
        $decoded = json_decode($json, true);

        $this->assertSame(['detail' => 'x'], $decoded['error']['data']);
    }

    public function testEncodeErrorV11(): void
    {
        $error = new Error(Version::V1_1, ErrorCode::ParseError, 'Bad JSON', null, 1);
        $json = $this->codec->encodeError($error);
        $decoded = json_decode($json, true);

        $this->assertSame('1.1', $decoded['version']);
        $this->assertSame('JSONRPCError', $decoded['error']['name']);
        $this->assertSame(-32700, $decoded['error']['code']);
        $this->assertSame('Bad JSON', $decoded['error']['message']);
        $this->assertArrayNotHasKey('error', $decoded['error']); // no data subfield
    }

    public function testEncodeErrorV11WithData(): void
    {
        $error = new Error(Version::V1_1, ErrorCode::InternalError, 'fail', 'details', 1);
        $json = $this->codec->encodeError($error);
        $decoded = json_decode($json, true);

        $this->assertSame('details', $decoded['error']['error']);
    }

    public function testEncodeErrorWithIntCode(): void
    {
        $error = new Error(Version::V2_0, -32050, 'Custom', null, 1);
        $json = $this->codec->encodeError($error);
        $decoded = json_decode($json, true);

        $this->assertSame(-32050, $decoded['error']['code']);
    }

    // --- encodeBatch ---

    public function testEncodeBatch(): void
    {
        $items = [
            new Response(Version::V2_0, 1, 1),
            new Error(Version::V2_0, ErrorCode::MethodNotFound, 'nope', null, 2),
        ];
        $json = $this->codec->encodeBatch($items);
        $decoded = json_decode($json, true);

        $this->assertCount(2, $decoded);
        $this->assertArrayHasKey('result', $decoded[0]);
        $this->assertArrayHasKey('error', $decoded[1]);
    }

    public function testEncodeBatchEmptyReturnsEmptyString(): void
    {
        $this->assertSame('', $this->codec->encodeBatch([]));
    }

    // --- encodeRequest ---

    public function testEncodeRequestV20(): void
    {
        $request = new Request(Version::V2_0, 'math.add', [1, 2], 1);
        $json = $this->codec->encodeRequest($request);
        $decoded = json_decode($json, true);

        $this->assertSame('2.0', $decoded['jsonrpc']);
        $this->assertSame('math.add', $decoded['method']);
        $this->assertSame([1, 2], $decoded['params']);
        $this->assertSame(1, $decoded['id']);
    }

    public function testEncodeRequestNotificationOmitsId(): void
    {
        $request = new Request(Version::V2_0, 'notify', [], null);
        $json = $this->codec->encodeRequest($request);
        $decoded = json_decode($json, true);

        $this->assertArrayNotHasKey('id', $decoded);
    }

    public function testEncodeRequestEmptyParamsOmitted(): void
    {
        $request = new Request(Version::V2_0, 'test', [], 1);
        $json = $this->codec->encodeRequest($request);
        $decoded = json_decode($json, true);

        $this->assertArrayNotHasKey('params', $decoded);
    }

    public function testEncodeRequestV11(): void
    {
        $request = new Request(Version::V1_1, 'test', [1], 1);
        $json = $this->codec->encodeRequest($request);
        $decoded = json_decode($json, true);

        $this->assertSame('1.1', $decoded['version']);
        $this->assertArrayNotHasKey('jsonrpc', $decoded);
    }

    // --- Round-trip ---

    public function testRoundTrip(): void
    {
        $original = new Request(Version::V2_0, 'math.add', [1, 2], 42);
        $json = $this->codec->encodeRequest($original);
        $decoded = $this->codec->decode($json);

        $this->assertInstanceOf(Request::class, $decoded);
        $this->assertSame($original->version, $decoded->version);
        $this->assertSame($original->method, $decoded->method);
        $this->assertSame($original->params, $decoded->params);
        $this->assertSame($original->id, $decoded->id);
    }
}
