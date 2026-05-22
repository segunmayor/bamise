<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Event\Examples;

use Bamise\Contract\Event\AfterCreate;
use Bamise\Contract\Event\AfterDelete;
use Bamise\Contract\Event\AfterUpdate;
use Bamise\Contract\Event\BeforeCreate;
use Bamise\Contract\Event\BeforeDelete;
use Bamise\Contract\Event\BeforeUpdate;
use Bamise\Contract\Event\DomainEventInterface;
use Bamise\Infrastructure\Event\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

final class LogLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            BeforeCreate::class => ['onLifecycle', 10],
            AfterCreate::class => 'onLifecycle',
            BeforeUpdate::class => 'onLifecycle',
            AfterUpdate::class => 'onLifecycle',
            BeforeDelete::class => 'onLifecycle',
            AfterDelete::class => 'onLifecycle',
        ];
    }

    public function onLifecycle(DomainEventInterface $event): void
    {
        $this->logger->info('Lifecycle event dispatched', [
            'event' => $event::class,
        ]);
    }
}
