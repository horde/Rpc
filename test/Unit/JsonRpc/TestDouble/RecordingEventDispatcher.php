<?php

declare(strict_types=1);

namespace Horde\Rpc\Test\Unit\JsonRpc\TestDouble;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Records dispatched events for test assertions.
 */
class RecordingEventDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }

    /**
     * @return list<object>
     */
    public function getEventsOfType(string $class): array
    {
        return array_values(
            array_filter($this->events, fn(object $e) => $e instanceof $class)
        );
    }
}
