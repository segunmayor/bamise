<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Event;

interface EventSubscriberInterface
{
    /**
     * @return array<class-string, string|array{string, int}>
     */
    public function getSubscribedEvents(): array;
}
