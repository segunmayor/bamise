<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Event;

use Bamise\Contract\Event\AfterCreate;
use Bamise\Contract\Event\BeforeCreate;
use Bamise\Contract\Event\DomainEventInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Infrastructure\Event\EventSubscriberInterface;
use Bamise\Infrastructure\Event\ListenerRegistry;
use Bamise\Infrastructure\Event\SubscriberLoader;
use Bamise\Infrastructure\Event\SyncEventDispatcher;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class SubscriberLoaderTest extends TestCase
{
    public function test_loads_subscriber_methods_with_priorities(): void
    {
        $subscriber = new TestLifecycleSubscriber();
        $dispatcher = new SyncEventDispatcher(new ListenerRegistry());
        (new SubscriberLoader())->load($dispatcher, $subscriber);

        $context = new CrudContext(
            OperationType::Create,
            'users',
            [],
            null,
            new FakeCrudRequest('POST', '/users'),
        );
        $dispatcher->dispatch(new BeforeCreate($context));
        $dispatcher->dispatch(new AfterCreate($context, ['id' => 1]));

        self::assertSame(['before', 'after'], $subscriber->log);
    }
}

final class TestLifecycleSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    public array $log = [];

    public function getSubscribedEvents(): array
    {
        return [
            BeforeCreate::class => ['onBefore', 10],
            AfterCreate::class => 'onAfter',
        ];
    }

    public function onBefore(DomainEventInterface $event): void
    {
        unset($event);
        $this->log[] = 'before';
    }

    public function onAfter(DomainEventInterface $event): void
    {
        unset($event);
        $this->log[] = 'after';
    }
}
