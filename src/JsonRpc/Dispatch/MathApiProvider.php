<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc\Dispatch;

use Horde\Rpc\Dispatch\ApiCallContext;
use Horde\Rpc\Dispatch\ApiProvider;
use Horde\Rpc\Dispatch\CallableMapProvider;
use Horde\Rpc\Dispatch\MethodDescriptor;
use Horde\Rpc\Dispatch\MethodInvoker;
use Horde\Rpc\Dispatch\Result;
use Horde\Rpc\JsonRpc\Exception\InvalidParamsException;

/**
 * Illustrational API provider with basic math operations.
 *
 * Registers four methods: math.add, math.subtract, math.multiply, math.divide.
 * Serves as documentation-by-code for how to build an ApiProvider.
 *
 * Example usage:
 *
 *     $provider = MathApiProvider::create();
 *     $dispatcher = new Dispatcher($provider, $provider);
 */
final class MathApiProvider implements ApiProvider, MethodInvoker
{
    private readonly CallableMapProvider $inner;

    private function __construct()
    {
        $this->inner = new CallableMapProvider(
            [
                'math.add' => fn(float $a, float $b): float => $a + $b,
                'math.subtract' => fn(float $a, float $b): float => $a - $b,
                'math.multiply' => fn(float $a, float $b): float => $a * $b,
                'math.divide' => static function (float $a, float $b): float {
                    if ($b == 0.0) {
                        throw new InvalidParamsException('Division by zero');
                    }

                    return $a / $b;
                },
            ],
            [
                'math.add' => new MethodDescriptor(
                    'math.add',
                    'Add two numbers',
                    [
                        ['name' => 'a', 'type' => 'float', 'required' => true],
                        ['name' => 'b', 'type' => 'float', 'required' => true],
                    ],
                    'float',
                    inputSchema: [
                        'type' => 'object',
                        'properties' => [
                            'a' => ['type' => 'number', 'description' => 'First number'],
                            'b' => ['type' => 'number', 'description' => 'Second number'],
                        ],
                        'required' => ['a', 'b'],
                    ],
                ),
                'math.subtract' => new MethodDescriptor(
                    'math.subtract',
                    'Subtract b from a',
                    [
                        ['name' => 'a', 'type' => 'float', 'required' => true],
                        ['name' => 'b', 'type' => 'float', 'required' => true],
                    ],
                    'float',
                    inputSchema: [
                        'type' => 'object',
                        'properties' => [
                            'a' => ['type' => 'number', 'description' => 'Minuend'],
                            'b' => ['type' => 'number', 'description' => 'Subtrahend'],
                        ],
                        'required' => ['a', 'b'],
                    ],
                ),
                'math.multiply' => new MethodDescriptor(
                    'math.multiply',
                    'Multiply two numbers',
                    [
                        ['name' => 'a', 'type' => 'float', 'required' => true],
                        ['name' => 'b', 'type' => 'float', 'required' => true],
                    ],
                    'float',
                    inputSchema: [
                        'type' => 'object',
                        'properties' => [
                            'a' => ['type' => 'number', 'description' => 'First factor'],
                            'b' => ['type' => 'number', 'description' => 'Second factor'],
                        ],
                        'required' => ['a', 'b'],
                    ],
                ),
                'math.divide' => new MethodDescriptor(
                    'math.divide',
                    'Divide a by b (throws InvalidParamsException on division by zero)',
                    [
                        ['name' => 'a', 'type' => 'float', 'required' => true],
                        ['name' => 'b', 'type' => 'float', 'required' => true],
                    ],
                    'float',
                    inputSchema: [
                        'type' => 'object',
                        'properties' => [
                            'a' => ['type' => 'number', 'description' => 'Dividend'],
                            'b' => ['type' => 'number', 'description' => 'Divisor (must not be zero)'],
                        ],
                        'required' => ['a', 'b'],
                    ],
                ),
            ],
        );
    }

    public static function create(): self
    {
        return new self();
    }

    public function hasMethod(string $method, ?ApiCallContext $context = null): bool
    {
        return $this->inner->hasMethod($method);
    }

    public function getMethodDescriptor(string $method, ?ApiCallContext $context = null): ?MethodDescriptor
    {
        return $this->inner->getMethodDescriptor($method);
    }

    public function listMethods(?ApiCallContext $context = null): array
    {
        return $this->inner->listMethods();
    }

    public function invoke(string $method, array $params, ?ApiCallContext $context = null): Result
    {
        return $this->inner->invoke($method, $params);
    }
}
