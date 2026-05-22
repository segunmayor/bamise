<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Event;

use Bamise\Contract\Event\DomainEventInterface;

final class ListenerRegistry
{
    /** @var array<string, list<PrioritizedListener>> */
    private array $listeners = [];

    public function add(string $eventClass, callable $listener, int $priority = 0, bool $async = false): void
    {
        $this->listeners[$eventClass][] = new PrioritizedListener($listener, $priority, $async);
    }

    /**
     * @return list<PrioritizedListener>
     */
    public function forEvent(object $event): array
    {
        $merged = [];

        foreach ($this->resolveEventTypes($event) as $eventType) {
            if (! isset($this->listeners[$eventType])) {
                continue;
            }

            foreach ($this->listeners[$eventType] as $listener) {
                $merged[] = $listener;
            }
        }

        usort(
            $merged,
            static fn (PrioritizedListener $a, PrioritizedListener $b): int => $b->priority <=> $a->priority,
        );

        return $merged;
    }

    /**
     * @return list<class-string>
     */
    private function resolveEventTypes(object $event): array
    {
        $types = [get_class($event)];

        if ($event instanceof DomainEventInterface) {
            foreach (class_implements($event) ?: [] as $interface) {
                $types[] = $interface;
            }
        }

        return array_values(array_unique($types));
    }
}
