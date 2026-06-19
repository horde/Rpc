<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\Soap\Exception;

use Horde\Exception\HordeThrowable;
use Horde\Rpc\Soap\Exception\SoapException;
use Horde\Rpc\Soap\Exception\SoapThrowable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SoapFault;

#[CoversClass(SoapException::class)]
class SoapExceptionTest extends TestCase
{
    public function testDefaultFaultCodeIsServer(): void
    {
        $e = new SoapException('boom');

        $this->assertSame('Server', $e->getFaultCode());
        $this->assertNull($e->getFaultActor());
        $this->assertNull($e->getDetail());
        $this->assertSame('boom', $e->getMessage());
    }

    public function testCustomFieldsArePreserved(): void
    {
        $detail = ['errno' => 17, 'where' => 'database'];

        $e = new SoapException(
            message: 'database is down',
            faultCode: 'Client',
            faultActor: 'http://example.com/db-service',
            detail: $detail,
        );

        $this->assertSame('Client', $e->getFaultCode());
        $this->assertSame('http://example.com/db-service', $e->getFaultActor());
        $this->assertSame($detail, $e->getDetail());
    }

    public function testImplementsSoapThrowableAndHordeThrowable(): void
    {
        $e = new SoapException('boom');

        $this->assertInstanceOf(SoapThrowable::class, $e);
        $this->assertInstanceOf(HordeThrowable::class, $e);
    }

    public function testFromSoapFaultPreservesSoap11Fields(): void
    {
        $detail = (object) ['code' => 'E_AUTH'];

        $fault = new SoapFault('Client', 'authentication failed', 'http://example.com/auth', $detail);

        $e = SoapException::fromSoapFault($fault);

        $this->assertSame('Client', $e->getFaultCode());
        $this->assertSame('http://example.com/auth', $e->getFaultActor());
        $this->assertSame($detail, $e->getDetail());
        $this->assertSame('authentication failed', $e->getMessage());
        $this->assertSame($fault, $e->getPrevious());
    }

    public function testFromSoapFaultPreservesEmptyActorAsNull(): void
    {
        $fault = new SoapFault('Server', 'oops');

        $e = SoapException::fromSoapFault($fault);

        $this->assertNull($e->getFaultActor());
        $this->assertNull($e->getDetail());
    }
}
