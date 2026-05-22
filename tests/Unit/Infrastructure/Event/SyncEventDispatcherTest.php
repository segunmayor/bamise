<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Event;

use Bamise\Contract\Event\BeforeCreate;
use Bamise\Contract\Event\DomainEventInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Infrastructure\Event\ListenerRegistry;
use Bamise\Infrastructure\Event\SyncEventDispatcher;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class SyncEventDispatcherTest extends TestCase
{
    public function test_invokes_listeners_in_descending_priority_order(): void
    {
        $order = [];
        $dispatcher = new SyncEventDispatcher(new ListenerRegistry());
        $dispatcher->subscribe(BeforeCreate::class, static function () use (&$order): void {
            $order[] = 'low';
        }, 0);
        $dispatcher->subscribe(BeforeCreate::class, static function () use (&$order): void {
            $order[] = 'high';
        }, 100);
        $dispatcher->subscribe(BeforeCreate::class, static function () use (&$order): void {
            $order[] = 'mid';
        }, 50);

        $dispatcher->dispatch($this->beforeCreateEvent());

        self::assertSame(['high', 'mid', 'low'], $order);
    }

    public function test_invokes_listeners_registered_for_parent_interface(): void
    {
        $called = false;
        $dispatcher = new SyncEventDispatcher(new ListenerRegistry());
        $dispatcher->subscribe(DomainEventInterface::class, static function () use (&$called): void {
            $called = true;
        });

        $dispatcher->dispatch($this->beforeCreateEvent());

        self::assertTrue($called);
    }

    public function test_stops_propagation_when_listener_returns_false(): void
    {
        $order = [];
        $dispatcher = new SyncEventDispatcher(new ListenerRegistry());
        $dispatcher->subscribe(BeforeCreate::class, static function () use (&$order): bool {
            $order[] = 'first';

            return false;
        }, 100);
        $dispatcher->subscribe(BeforeCreate::class, static function () use (&$order): void {
            $order[] = 'second';
        }, 0);

        $dispatcher->dispatch($this->beforeCreateEvent());

        self::assertSame(['first'], $order);
    }

    private function beforeCreateEvent(): BeforeCreate
    {
        return new BeforeCreate(
            new CrudContext(
                OperationType::Create,
                'users',
                ['name' => 'Ada'],
                null,
                new FakeCrudRequest('POST', '/users', ['name' => 'Ada']),
            ),
        );
    }
}
