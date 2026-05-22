<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Event;

readonly class PrioritizedListener
{
    /**
     * @param callable $listener
     */
    public function __construct(
        public mixed $listener,
        public int $priority = 0,
        public bool $async = false,
    ) {
    }
}
