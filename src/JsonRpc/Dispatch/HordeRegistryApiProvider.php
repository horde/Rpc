<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc\Dispatch;

use Horde_Exception;
use Horde_Registry;
use Horde\Rpc\JsonRpc\Exception\InternalErrorException;
use Horde\Rpc\JsonRpc\Exception\MethodNotFoundException;

/**
 * API provider bridging to Horde_Registry.
 *
 * Translates JSON-RPC dot-notation (calendar.list) to the slash-notation
 * (calendar/list) expected by Horde_Registry. This is the migration path
 * for existing Horde installations moving from the legacy Horde_Rpc_Jsonrpc.
 *
 * Example usage:
 *
 *     $provider = new HordeRegistryApiProvider($registry);
 *     $dispatcher = new Dispatcher($provider, $provider);
 */
final class HordeRegistryApiProvider implements ApiProviderInterface, MethodInvokerInterface
{
    public function __construct(
        private readonly Horde_Registry $registry,
    ) {}

    public function hasMethod(string $method): bool
    {
        return (bool) $this->registry->hasMethod($this->dotToSlash($method));
    }

    public function getMethodDescriptor(string $method): ?MethodDescriptor
    {
        if (!$this->hasMethod($method)) {
            return null;
        }

        return new MethodDescriptor($method);
    }

    public function listMethods(): array
    {
        $descriptors = [];
        foreach ($this->registry->listMethods() as $slashMethod) {
            $dotMethod = $this->slashToDot($slashMethod);
            $descriptors[] = new MethodDescriptor($dotMethod);
        }

        return $descriptors;
    }

    public function invoke(string $method, array $params): Result
    {
        $slashMethod = $this->dotToSlash($method);

        if (!$this->registry->hasMethod($slashMethod)) {
            throw new MethodNotFoundException($method);
        }

        try {
            $result = $this->registry->call($slashMethod, $params);
        } catch (Horde_Exception $e) {
            throw new InternalErrorException($e->getMessage(), previous: $e);
        }

        return new Result($result);
    }

    private function dotToSlash(string $method): string
    {
        return str_replace('.', '/', $method);
    }

    private function slashToDot(string $method): string
    {
        return str_replace('/', '.', $method);
    }
}
