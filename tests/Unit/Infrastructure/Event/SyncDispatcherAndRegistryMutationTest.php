<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Event;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Event\AfterCreate;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Infrastructure\Event\ListenerRegistry;
use Bamise\Infrastructure\Event\SyncEventDispatcher;
use Bamise\Infrastructure\Queue\InMemoryQueue;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for SyncEventDispatcher and ListenerRegistry.
 *
 * Kills escaped mutants:
 * SyncEventDispatcher:
 * - Line 28: Continue_ (async listener skips sync execution)
 * - Lines 40,45: DecrementInteger/IncrementInteger on priority params
 * - Line 53: Throw_ (async without queue throws)
 *
 * ListenerRegistry:
 * - Line 14: DecrementInteger/IncrementInteger on add()
 * - Line 51: InstanceOf_ on DomainEventInterface check
 * - Line 57: UnwrapArrayUnique/UnwrapArrayValues on resolveEventTypes
 */
final class SyncDispatcherAndRegistryMutationTest extends TestCase
{
    private function makeEvent(): AfterCreate
    {
        return new AfterCreate(new CrudContext(OperationType::Create, 'items', [], null, new FakeCrudRequest()));
    }

    // ── SyncEventDispatcher: Line 28 Continue_ ────────────────────────────────

    public function test_async_listener_is_not_invoked_synchronously(): void
    {
        $registry = new ListenerRegistry();
        $invoked = false;

        $registry->add(AfterCreate::class, function () use (&$invoked) {
            $invoked = true;
        }, priority: 0, async: true);

        $dispatcher = new SyncEventDispatcher($registry); // no queue

        // Async listener without queue should throw — Continue_ mutation would
        // skip the enqueue call and execute the listener synchronously instead.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/QueuePortInterface/');

        $dispatcher->dispatch($this->makeEvent());
    }

    public function test_sync_listener_is_invoked(): void
    {
        $registry = new ListenerRegistry();
        $called = false;

        $registry->add(\stdClass::class, function () use (&$called) {
            $called = true;
        }, priority: 0, async: false);

        $dispatcher = new SyncEventDispatcher($registry);
        $dispatcher->dispatch(new \stdClass());

        self::assertTrue($called);
    }

    // ── SyncEventDispatcher: Line 53 Throw_ ─────────────────────────────────

    public function test_async_listener_without_queue_throws(): void
    {
        $registry = new ListenerRegistry();
        $registry->add(AfterCreate::class, function () {
        }, priority: 0, async: true);

        $dispatcher = new SyncEventDispatcher($registry, queue: null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/QueuePortInterface/');

        $dispatcher->dispatch($this->makeEvent());
    }

    public function test_async_listener_with_queue_enqueues_job(): void
    {
        $registry = new ListenerRegistry();
        $registry->add(AfterCreate::class, function () {
        }, priority: 0, async: true);

        $queue = new InMemoryQueue();
        $dispatcher = new SyncEventDispatcher($registry, queue: $queue);
        $dispatcher->dispatch($this->makeEvent());

        self::assertSame(1, $queue->count());
    }

    // ── SyncEventDispatcher: propagation stop on false ────────────────────────

    public function test_returning_false_stops_propagation(): void
    {
        $registry = new ListenerRegistry();
        $callCount = 0;

        $registry->add(\stdClass::class, function () use (&$callCount) {
            $callCount++;

            return false; // stop propagation
        }, priority: 100);

        $registry->add(\stdClass::class, function () use (&$callCount) {
            $callCount++;
        }, priority: 50);

        $dispatcher = new SyncEventDispatcher($registry);
        $dispatcher->dispatch(new \stdClass());

        self::assertSame(1, $callCount);
    }

    // ── SyncEventDispatcher: subscribe() priority ─────────────────────────────

    public function test_subscribe_with_priority_orders_correctly(): void
    {
        $registry = new ListenerRegistry();
        $order = [];

        $registry->add(\stdClass::class, function () use (&$order) {
            $order[] = 'low';
        }, priority: 10);
        $registry->add(\stdClass::class, function () use (&$order) {
            $order[] = 'high';
        }, priority: 100);

        $dispatcher = new SyncEventDispatcher($registry);
        $dispatcher->dispatch(new \stdClass());

        // Higher priority should run first
        self::assertSame(['high', 'low'], $order);
    }

    // ── ListenerRegistry: Line 14 DecrementInteger/IncrementInteger ──────────

    public function test_add_listener_makes_it_dispatchable(): void
    {
        $registry = new ListenerRegistry();
        $called = false;

        $registry->add(\stdClass::class, function () use (&$called) {
            $called = true;
        });

        $dispatcher = new SyncEventDispatcher($registry);
        $dispatcher->dispatch(new \stdClass());

        self::assertTrue($called);
    }

    public function test_add_multiple_listeners_all_called(): void
    {
        $registry = new ListenerRegistry();
        $count = 0;

        $registry->add(\stdClass::class, function () use (&$count) {
            $count++;
        });
        $registry->add(\stdClass::class, function () use (&$count) {
            $count++;
        });

        $dispatcher = new SyncEventDispatcher($registry);
        $dispatcher->dispatch(new \stdClass());

        self::assertSame(2, $count);
    }

    // ── ListenerRegistry: Line 51 InstanceOf_ on DomainEventInterface ─────────

    public function test_non_domain_event_resolves_only_concrete_class(): void
    {
        $registry = new ListenerRegistry();

        $called = [];
        $registry->add(\stdClass::class, function () use (&$called) {
            $called[] = 'stdClass';
        });

        $listeners = $registry->forEvent(new \stdClass());

        self::assertCount(1, $listeners);
    }

    // ── ListenerRegistry: Line 57 UnwrapArrayUnique/UnwrapArrayValues ─────────

    public function test_event_types_are_unique_in_resolution(): void
    {
        $registry = new ListenerRegistry();
        $count = 0;

        $registry->add(\stdClass::class, function () use (&$count) {
            $count++;
        });

        // Dispatch once - if resolveEventTypes had duplicates without array_unique,
        // the listener could be called multiple times
        $dispatcher = new SyncEventDispatcher($registry);
        $dispatcher->dispatch(new \stdClass());

        self::assertSame(1, $count);
    }

    // ── ListenerRegistry priority ordering ────────────────────────────────────

    public function test_listeners_sorted_by_priority_descending(): void
    {
        $registry = new ListenerRegistry();
        $order = [];

        $registry->add(\stdClass::class, function () use (&$order) {
            $order[] = 'p10';
        }, priority: 10);
        $registry->add(\stdClass::class, function () use (&$order) {
            $order[] = 'p50';
        }, priority: 50);
        $registry->add(\stdClass::class, function () use (&$order) {
            $order[] = 'p30';
        }, priority: 30);

        $listeners = $registry->forEvent(new \stdClass());

        // Priority 50 > 30 > 10
        self::assertSame(50, $listeners[0]->priority);
        self::assertSame(30, $listeners[1]->priority);
        self::assertSame(10, $listeners[2]->priority);
    }

    // ── SyncEventDispatcher subscribeAsync ────────────────────────────────────

    public function test_subscribe_async_enqueues_job_on_dispatch(): void
    {
        $registry = new ListenerRegistry();
        $queue = new InMemoryQueue();
        $dispatcher = new SyncEventDispatcher($registry, queue: $queue);

        $dispatcher->subscribeAsync(AfterCreate::class, function () {
        }, priority: 0);
        $dispatcher->dispatch($this->makeEvent());

        self::assertSame(1, $queue->count());
    }

    // ── SyncEventDispatcher Lines 40,45: subscribe() priority values ─────────

    public function test_subscribe_default_priority_zero(): void
    {
        $registry = new ListenerRegistry();
        $registry->add(\stdClass::class, function () {
        }, priority: 0);

        $listeners = $registry->forEvent(new \stdClass());

        self::assertSame(0, $listeners[0]->priority);
    }

    public function test_subscribe_explicit_priority_is_stored(): void
    {
        $registry = new ListenerRegistry();
        $registry->add(\stdClass::class, function () {
        }, priority: 42);

        $listeners = $registry->forEvent(new \stdClass());

        self::assertSame(42, $listeners[0]->priority);
    }
}
