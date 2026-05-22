<?php

declare(strict_types=1);

namespace Bamise\Contract;

interface EventDispatcherPortInterface
{
    public function dispatch(object $event): void;

    public function subscribe(string $eventClass, callable $listener, int $priority = 0): void;
}
