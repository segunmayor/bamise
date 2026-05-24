<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Event;

use Bamise\Contract\Event\BeforeCreate;
use Bamise\Contract\Event\DomainEventInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Infrastructure\Event\ListenerRegistry;
use Bamise\Infrastructure\Event\PrioritizedListener;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class ListenerRegistryTest extends TestCase
{
    public function test_returns_empty_array_when_no_listeners_registered(): void
    {
        $registry = new ListenerRegistry();

        self::assertSame([], $registry->forEvent($this->makeBeforeCreate()));
    }

    public function test_returns_listener_for_exact_event_class(): void
    {
        $registry = new ListenerRegistry();
        $listener = static function (object $e): void {
        };
        $registry->add(BeforeCreate::class, $listener);

        $result = $registry->forEvent($this->makeBeforeCreate());

        self::assertCount(1, $result);
        self::assertInstanceOf(PrioritizedListener::class, $result[0]);
    }

    public function test_sorts_multiple_listeners_by_descending_priority(): void
    {
        $order = [];
        $registry = new ListenerRegistry();
        $registry->add(BeforeCreate::class, static function () use (&$order): void {
            $order[] = 'low';
        }, 0);
        $registry->add(BeforeCreate::class, static function () use (&$order): void {
            $order[] = 'high';
        }, 100);
        $registry->add(BeforeCreate::class, static function () use (&$order): void {
            $order[] = 'mid';
        }, 50);

        $result = $registry->forEvent($this->makeBeforeCreate());

        foreach ($result as $entry) {
            ($entry->listener)();
        }

        self::assertSame(['high', 'mid', 'low'], $order);
    }

    public function test_resolves_interface_listeners_for_domain_events(): void
    {
        $registry = new ListenerRegistry();
        $called = false;
        $registry->add(DomainEventInterface::class, static function () use (&$called): void {
            $called = true;
        });

        $result = $registry->forEvent($this->makeBeforeCreate());

        self::assertCount(1, $result);
        ($result[0]->listener)();
        self::assertTrue($called);
    }

    public function test_merges_concrete_and_interface_listeners_sorted_by_priority(): void
    {
        $order = [];
        $registry = new ListenerRegistry();
        $registry->add(BeforeCreate::class, static function () use (&$order): void {
            $order[] = 'concrete';
        }, 10);
        $registry->add(DomainEventInterface::class, static function () use (&$order): void {
            $order[] = 'interface';
        }, 20);

        $result = $registry->forEvent($this->makeBeforeCreate());

        foreach ($result as $entry) {
            ($entry->listener)();
        }

        self::assertSame(['interface', 'concrete'], $order);
    }

    public function test_does_not_expand_interfaces_for_non_domain_events(): void
    {
        $registry = new ListenerRegistry();
        $registry->add(DomainEventInterface::class, static function (): void {
        });

        $result = $registry->forEvent(new \stdClass());

        self::assertSame([], $result);
    }

    public function test_event_class_with_no_registered_listeners_returns_empty_array(): void
    {
        $registry = new ListenerRegistry();
        $registry->add(BeforeCreate::class, static function (): void {
        });

        $unrelatedEvent = new class implements DomainEventInterface {
        };

        self::assertSame([], $registry->forEvent($unrelatedEvent));
    }

    public function test_preserves_async_flag_on_prioritized_listener(): void
    {
        $registry = new ListenerRegistry();
        $registry->add(BeforeCreate::class, static function (): void {
        }, 0, async: true);

        $result = $registry->forEvent($this->makeBeforeCreate());

        self::assertTrue($result[0]->async);
    }

    public function test_preserves_sync_flag_by_default(): void
    {
        $registry = new ListenerRegistry();
        $registry->add(BeforeCreate::class, static function (): void {
        });

        $result = $registry->forEvent($this->makeBeforeCreate());

        self::assertFalse($result[0]->async);
    }

    public function test_multiple_adds_accumulate_independently(): void
    {
        $registry = new ListenerRegistry();
        $registry->add(BeforeCreate::class, static function (): void {
        }, 5);
        $registry->add(BeforeCreate::class, static function (): void {
        }, 5);
        $registry->add(BeforeCreate::class, static function (): void {
        }, 5);

        self::assertCount(3, $registry->forEvent($this->makeBeforeCreate()));
    }

    private function makeBeforeCreate(): BeforeCreate
    {
        return new BeforeCreate(
            new CrudContext(
                OperationType::Create,
                'users',
                ['name' => 'Ada'],
                null,
                new FakeCrudRequest('POST', '/users'),
            ),
        );
    }
}
