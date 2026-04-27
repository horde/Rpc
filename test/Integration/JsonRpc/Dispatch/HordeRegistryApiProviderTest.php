<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Integration\JsonRpc\Dispatch;

use Horde_Exception;
use Horde_Registry;
use Horde\Rpc\Dispatch\MethodDescriptor;
use Horde\Rpc\JsonRpc\Dispatch\HordeRegistryApiProvider;
use Horde\Rpc\JsonRpc\Exception\InternalErrorException;
use Horde\Rpc\JsonRpc\Exception\MethodNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HordeRegistryApiProvider::class)]
class HordeRegistryApiProviderTest extends TestCase
{
    // --- Dot-to-slash translation ---

    public function testHasMethodTranslatesDotToSlash(): void
    {
        $registry = $this->createStub(Horde_Registry::class);
        $registry->method('hasMethod')
            ->willReturnCallback(fn(string $method) => $method === 'calendar/list' ? 'calendar' : false);

        $provider = new HordeRegistryApiProvider($registry);

        $this->assertTrue($provider->hasMethod('calendar.list'));
        $this->assertFalse($provider->hasMethod('calendar.nope'));
    }

    public function testHasMethodReturnsFalse(): void
    {
        $registry = $this->createStub(Horde_Registry::class);
        $registry->method('hasMethod')
            ->willReturn(false);

        $provider = new HordeRegistryApiProvider($registry);

        $this->assertFalse($provider->hasMethod('nonexistent.method'));
    }

    // --- Invoke ---

    public function testInvokeCallsRegistryWithSlashNotation(): void
    {
        $registry = $this->createStub(Horde_Registry::class);
        $registry->method('hasMethod')
            ->willReturnCallback(fn(string $method) => $method === 'tasks/list' ? 'tasks' : false);
        $registry->method('call')
            ->willReturnCallback(function (string $method, array $params) {
                $this->assertSame('tasks/list', $method);
                $this->assertSame(['open'], $params);

                return ['task1', 'task2'];
            });

        $provider = new HordeRegistryApiProvider($registry);
        $result = $provider->invoke('tasks.list', ['open']);

        $this->assertSame(['task1', 'task2'], $result->value);
    }

    public function testInvokeThrowsMethodNotFoundForUnknown(): void
    {
        $registry = $this->createStub(Horde_Registry::class);
        $registry->method('hasMethod')
            ->willReturn(false);

        $provider = new HordeRegistryApiProvider($registry);

        $this->expectException(MethodNotFoundException::class);
        $provider->invoke('nope.method', []);
    }

    public function testInvokeWrapsHordeException(): void
    {
        $registry = $this->createStub(Horde_Registry::class);
        $registry->method('hasMethod')
            ->willReturn('app');
        $registry->method('call')
            ->willThrowException(new Horde_Exception('Backend failure'));

        $provider = new HordeRegistryApiProvider($registry);

        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessage('Backend failure');
        $provider->invoke('app.action', []);
    }

    // --- listMethods slash-to-dot translation ---

    public function testListMethodsTranslatesSlashToDot(): void
    {
        $registry = $this->createStub(Horde_Registry::class);
        $registry->method('listMethods')
            ->willReturn(['calendar/list', 'calendar/add', 'tasks/list']);

        $provider = new HordeRegistryApiProvider($registry);
        $methods = $provider->listMethods();

        $this->assertCount(3, $methods);
        $names = array_map(fn(MethodDescriptor $d) => $d->name, $methods);
        $this->assertSame(['calendar.list', 'calendar.add', 'tasks.list'], $names);
    }

    public function testListMethodsReturnsEmptyForNoMethods(): void
    {
        $registry = $this->createStub(Horde_Registry::class);
        $registry->method('listMethods')
            ->willReturn([]);

        $provider = new HordeRegistryApiProvider($registry);

        $this->assertSame([], $provider->listMethods());
    }

    // --- getMethodDescriptor ---

    public function testGetMethodDescriptorForKnownMethod(): void
    {
        $registry = $this->createStub(Horde_Registry::class);
        $registry->method('hasMethod')
            ->willReturnCallback(fn(string $method) => $method === 'calendar/list' ? 'calendar' : false);

        $provider = new HordeRegistryApiProvider($registry);
        $desc = $provider->getMethodDescriptor('calendar.list');

        $this->assertNotNull($desc);
        $this->assertSame('calendar.list', $desc->name);
    }

    public function testGetMethodDescriptorReturnsNullForUnknown(): void
    {
        $registry = $this->createStub(Horde_Registry::class);
        $registry->method('hasMethod')
            ->willReturn(false);

        $provider = new HordeRegistryApiProvider($registry);

        $this->assertNull($provider->getMethodDescriptor('nope.method'));
    }
}
