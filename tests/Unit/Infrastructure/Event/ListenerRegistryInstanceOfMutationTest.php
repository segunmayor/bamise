<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Event;

use Bamise\Contract\Event\DomainEventInterface;
use Bamise\Infrastructure\Event\ListenerRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for ListenerRegistry.
 *
 * Kills escaped mutant:
 * - Line 51: InstanceOf_ — non-DomainEventInterface event should NOT trigger interface listeners
 */
final class ListenerRegistryInstanceOfMutationTest extends TestCase
{
    public function test_non_domain_event_does_not_resolve_interface_listeners(): void
    {
        $registry = new ListenerRegistry();
        $interfaceCalled = false;

        // Register a listener for an interface that our non-domain event implements
        $registry->add(\Countable::class, function () use (&$interfaceCalled) {
            $interfaceCalled = true;
        });

        // Dispatch a non-DomainEventInterface event that implements Countable
        $event = new class implements \Countable {
            public function count(): int
            {
                return 0;
            }
        };

        // With InstanceOf_ mutation (if true): resolveEventTypes includes all interfaces
        // → \Countable listener would be found → interfaceCalled = true
        // With original (DomainEventInterface check): non-domain event → only class name → listener NOT found
        $listeners = $registry->forEvent($event);

        self::assertCount(0, $listeners, 'Interface listeners must NOT be resolved for non-DomainEventInterface events');
        self::assertFalse($interfaceCalled);
    }

    public function test_domain_event_resolves_interface_listeners(): void
    {
        $registry = new ListenerRegistry();

        // Create a DomainEventInterface event that also implements another interface
        $event = new class implements DomainEventInterface, \Countable {
            public function count(): int
            {
                return 0;
            }
        };

        $registry->add(\Countable::class, function () {
        });

        $listeners = $registry->forEvent($event);

        // DomainEventInterface event → interface resolution enabled → Countable listener found
        self::assertCount(1, $listeners);
    }
}
