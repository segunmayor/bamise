<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Event;

use Bamise\Contract\EventDispatcherPortInterface;
use Bamise\Contract\QueuePortInterface;

final class SyncEventDispatcher implements EventDispatcherPortInterface
{
    public const string ASYNC_JOB = 'bamise.event.async_listener';

    public function __construct(
        private readonly ListenerRegistry $registry,
        private readonly ?QueuePortInterface $queue = null,
        private readonly EventPayloadEncoder $encoder = new EventPayloadEncoder(),
    ) {
    }

    public function dispatch(object $event): void
    {
        foreach ($this->registry->forEvent($event) as $entry) {
            if ($entry->async) {
                $this->enqueue($event);

                continue;
            }

            $result = ($entry->listener)($event);

            if ($result === false) {
                break;
            }
        }
    }

    public function subscribe(string $eventClass, callable $listener, int $priority = 0): void
    {
        $this->registry->add($eventClass, $listener, $priority, async: false);
    }

    public function subscribeAsync(string $eventClass, callable $listener, int $priority = 0): void
    {
        $this->registry->add($eventClass, $listener, $priority, async: true);
    }

    private function enqueue(object $event): void
    {
        if ($this->queue === null) {
            throw new \RuntimeException('QueuePortInterface is required for async event listeners.');
        }

        $job = new QueueJobPayload(
            self::ASYNC_JOB,
            $this->encoder->encode($event),
        );

        $this->queue->push($job->job, $job->payload);
    }
}
