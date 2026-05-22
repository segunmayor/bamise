<?php

declare(strict_types=1);

namespace Bamise\Tests\Fixtures;

use Bamise\Contract\EventDispatcherPortInterface;

final class FakeEventDispatcherPort implements EventDispatcherPortInterface
{
    /** @var list<object> */
    public array $dispatched = [];

    public function dispatch(object $event): void
    {
        $this->dispatched[] = $event;
    }

    public function subscribe(string $eventClass, callable $listener, int $priority = 0): void
    {
        unset($eventClass, $listener, $priority);
    }
}
