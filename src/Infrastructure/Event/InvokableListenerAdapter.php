<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Event;

/**
 * Adapts an object with __invoke(object $event) to a callable listener.
 */
final class InvokableListenerAdapter
{
    public function __construct(
        private readonly object $listener,
    ) {
    }

    public function __invoke(object $event): mixed
    {
        $listener = $this->listener;

        if (is_callable($listener)) {
            return $listener($event);
        }

        throw new \LogicException(
            sprintf('Listener of type "%s" is not callable.', $listener::class),
        );
    }
}
