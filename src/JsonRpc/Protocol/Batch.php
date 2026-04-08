<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\JsonRpc\Protocol;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * An ordered collection of JSON-RPC requests (2.0 batch).
 *
 * May contain Error objects for individually failed decode attempts.
 *
 * @implements IteratorAggregate<int, Request|Error>
 */
final readonly class Batch implements Countable, IteratorAggregate
{
    /**
     * @param list<Request|Error> $requests
     */
    public function __construct(
        public array $requests,
    ) {}

    public function count(): int
    {
        return count($this->requests);
    }

    /**
     * @return Traversable<int, Request|Error>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->requests);
    }
}
