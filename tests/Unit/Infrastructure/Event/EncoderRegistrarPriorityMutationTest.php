<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Event;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Event\AfterCreate;
use Bamise\Contract\Event\DomainEventInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Infrastructure\Event\AsyncListenerRegistrar;
use Bamise\Infrastructure\Event\EventPayloadEncoder;
use Bamise\Infrastructure\Event\ListenerRegistry;
use Bamise\Infrastructure\Event\SyncEventDispatcher;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for EventPayloadEncoder and AsyncListenerRegistrar.
 *
 * Kills escaped mutants:
 * - EventPayloadEncoder line 18: InstanceOf_ (non-DomainEventInterface throws)
 * - EventPayloadEncoder line 19: Throw_ (exception is actually thrown)
 * - AsyncListenerRegistrar line 17: DecrementInteger/IncrementInteger on default priority=0
 * - SyncEventDispatcher lines 40/45: DecrementInteger/IncrementInteger on default priority=0
 * - ListenerRegistry line 14: DecrementInteger/IncrementInteger on default priority=0
 * - SyncEventDispatcher line 28: Continue_ → break (async listener does not swallow sync listener)
 */
final class EncoderRegistrarPriorityMutationTest extends TestCase
{
    private function makeEvent(): AfterCreate
    {
        return new AfterCreate(
            new CrudContext(OperationType::Create, 'things', [], null, new FakeCrudRequest()),
        );
    }

    // ── EventPayloadEncoder line 18: InstanceOf_ ─────────────────────────────

    public function test_encoder_throws_for_non_domain_event_interface(): void
    {
        $encoder = new EventPayloadEncoder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Cannot encode event/');

        // stdClass does not implement DomainEventInterface
        $encoder->encode(new \stdClass());
    }

    // ── EventPayloadEncoder line 19: Throw_ ───────────────────────────────────

    public function test_encoder_exception_is_actually_thrown(): void
    {
        $encoder = new EventPayloadEncoder();

        try {
            $encoder->encode(new \stdClass());
            self::fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('stdClass', $e->getMessage());
        }
    }

    // ── ListenerRegistry line 14: default priority=0 ─────────────────────────

    public function test_registry_add_default_priority_is_zero(): void
    {
        $registry = new ListenerRegistry();
        $registry->add(\stdClass::class, function () {});

        $listeners = $registry->forEvent(new \stdClass());

        self::assertSame(0, $listeners[0]->priority);
    }

    public function test_registry_add_default_priority_is_not_negative_one(): void
    {
        $registry = new ListenerRegistry();
        $registry->add(\stdClass::class, function () {});

        $listeners = $registry->forEvent(new \stdClass());

        self::assertNotSame(-1, $listeners[0]->priority);
    }

    public function test_registry_add_default_priority_is_not_one(): void
    {
        $registry = new ListenerRegistry();
        $registry->add(\stdClass::class, function () {});

        $listeners = $registry->forEvent(new \stdClass());

        self::assertNotSame(1, $listeners[0]->priority);
    }

    // ── SyncEventDispatcher line 40: subscribe() default priority=0 ──────────

    public function test_dispatcher_subscribe_default_priority_is_zero(): void
    {
        $registry = new ListenerRegistry();
        $dispatcher = new SyncEventDispatcher($registry);

        // Call subscribe() without priority argument (uses default)
        $dispatcher->subscribe(\stdClass::class, function () {});

        $listeners = $registry->forEvent(new \stdClass());

        self::assertSame(0, $listeners[0]->priority);
    }

    public function test_dispatcher_subscribe_default_priority_not_negative(): void
    {
        $registry = new ListenerRegistry();
        $dispatcher = new SyncEventDispatcher($registry);
        $dispatcher->subscribe(\stdClass::class, function () {});

        $listeners = $registry->forEvent(new \stdClass());

        self::assertGreaterThanOrEqual(0, $listeners[0]->priority);
    }

    // ── SyncEventDispatcher line 45: subscribeAsync() default priority=0 ─────

    public function test_dispatcher_subscribe_async_default_priority_is_zero(): void
    {
        $registry = new ListenerRegistry();
        $dispatcher = new SyncEventDispatcher($registry);

        $dispatcher->subscribeAsync(AfterCreate::class, function () {});

        $listeners = $registry->forEvent($this->makeEvent());

        self::assertSame(0, $listeners[0]->priority);
    }

    // ── AsyncListenerRegistrar line 17: default priority=0 ───────────────────

    public function test_async_registrar_default_priority_is_zero(): void
    {
        $registry = new ListenerRegistry();
        $dispatcher = new SyncEventDispatcher($registry);
        $registrar = new AsyncListenerRegistrar($dispatcher);

        // Call subscribe() without priority (uses default)
        $registrar->subscribe(AfterCreate::class, function () {});

        $listeners = $registry->forEvent($this->makeEvent());

        self::assertSame(0, $listeners[0]->priority);
    }

    public function test_async_registrar_default_priority_not_minus_one(): void
    {
        $registry = new ListenerRegistry();
        $dispatcher = new SyncEventDispatcher($registry);
        $registrar = new AsyncListenerRegistrar($dispatcher);

        $registrar->subscribe(AfterCreate::class, function () {});

        $listeners = $registry->forEvent($this->makeEvent());

        self::assertNotSame(-1, $listeners[0]->priority);
    }

    public function test_async_registrar_default_priority_not_plus_one(): void
    {
        $registry = new ListenerRegistry();
        $dispatcher = new SyncEventDispatcher($registry);
        $registrar = new AsyncListenerRegistrar($dispatcher);

        $registrar->subscribe(AfterCreate::class, function () {});

        $listeners = $registry->forEvent($this->makeEvent());

        self::assertNotSame(1, $listeners[0]->priority);
    }

    // ── SyncEventDispatcher line 28: Continue_ — async doesn't swallow sync ──

    public function test_async_listener_does_not_prevent_subsequent_sync_listener(): void
    {
        $registry = new ListenerRegistry();
        $syncCalled = false;

        // Register async listener at higher priority (runs first in sorted order)
        $registry->add(AfterCreate::class, function () {}, priority: 100, async: true);
        // Register sync listener at lower priority
        $registry->add(AfterCreate::class, function () use (&$syncCalled) {
            $syncCalled = true;
        }, priority: 0, async: false);

        // Use a queue so async doesn't throw
        $queue = new \Bamise\Infrastructure\Queue\InMemoryQueue();
        $dispatcher = new SyncEventDispatcher($registry, queue: $queue);

        $dispatcher->dispatch($this->makeEvent());

        // Continue_ mutation would break out of loop after async, skipping sync listener
        self::assertTrue($syncCalled, 'Sync listener must be called even when async listener runs first');
        self::assertSame(1, $queue->count(), 'Async listener should have been enqueued');
    }
}
