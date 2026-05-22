<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Event;

/**
 * Registers listeners that are deferred to QueuePortInterface instead of running inline.
 */
final class AsyncListenerRegistrar
{
    public function __construct(
        private readonly SyncEventDispatcher $dispatcher,
    ) {
    }

    public function subscribe(string $eventClass, callable $listener, int $priority = 0): void
    {
        $this->dispatcher->subscribeAsync($eventClass, $listener, $priority);
    }
}
