<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Event;

use Bamise\Infrastructure\Event\EventSubscriberInterface;
use Bamise\Infrastructure\Event\SubscriberLoader;
use Bamise\Tests\Fixtures\FakeEventDispatcherPort;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for SubscriberLoader.
 *
 * Kills escaped mutants:
 * - Line 14: Throw_ when subscriber is not EventSubscriberInterface
 * - Lines 21-26: string vs array config parsing
 * - Line 28: Throw_ for invalid config
 * - Line 29: CastString on $eventClass
 * - Line 34: Throw_ when method doesn't exist on subscriber
 */
final class SubscriberLoaderMutationTest extends TestCase
{
    private SubscriberLoader $loader;
    private FakeEventDispatcherPort $dispatcher;

    protected function setUp(): void
    {
        $this->loader = new SubscriberLoader();
        $this->dispatcher = new FakeEventDispatcherPort();
    }

    // ── Line 14: Throw_ for non-EventSubscriberInterface ─────────────────────

    public function test_non_subscriber_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/EventSubscriberInterface/');

        $this->loader->load($this->dispatcher, new \stdClass());
    }

    public function test_anonymous_class_without_interface_throws(): void
    {
        $notASubscriber = new class {
            public function handle(): void {}
        };

        $this->expectException(InvalidArgumentException::class);

        $this->loader->load($this->dispatcher, $notASubscriber);
    }

    // ── Lines 21-26: string config (method only) ──────────────────────────────

    public function test_string_config_subscribes_with_zero_priority(): void
    {
        $subscriber = new class implements EventSubscriberInterface {
            public function getSubscribedEvents(): array
            {
                return ['SomeEvent' => 'onSomeEvent'];
            }

            public function onSomeEvent(object $event): void {}
        };

        $this->loader->load($this->dispatcher, $subscriber);

        $subscriptions = $this->dispatcher->subscriptions;
        self::assertArrayHasKey('SomeEvent', $subscriptions);
        self::assertSame(0, $subscriptions['SomeEvent'][0]['priority']);
    }

    // ── Lines 24-26: array config [method, priority] ──────────────────────────

    public function test_array_config_with_priority_subscribes_correctly(): void
    {
        $subscriber = new class implements EventSubscriberInterface {
            public function getSubscribedEvents(): array
            {
                return ['OrderPlaced' => ['handleOrder', 50]];
            }

            public function handleOrder(object $event): void {}
        };

        $this->loader->load($this->dispatcher, $subscriber);

        $subscriptions = $this->dispatcher->subscriptions;
        self::assertArrayHasKey('OrderPlaced', $subscriptions);
        self::assertSame(50, $subscriptions['OrderPlaced'][0]['priority']);
    }

    public function test_array_config_without_priority_defaults_to_zero(): void
    {
        $subscriber = new class implements EventSubscriberInterface {
            public function getSubscribedEvents(): array
            {
                return ['UserCreated' => ['onUserCreated']];
            }

            public function onUserCreated(object $event): void {}
        };

        $this->loader->load($this->dispatcher, $subscriber);

        $subscriptions = $this->dispatcher->subscriptions;
        self::assertArrayHasKey('UserCreated', $subscriptions);
        self::assertSame(0, $subscriptions['UserCreated'][0]['priority']);
    }

    // ── Line 28: Throw_ for invalid config (neither string nor array with [0]) ─

    public function test_invalid_config_integer_throws(): void
    {
        $subscriber = new class implements EventSubscriberInterface {
            public function getSubscribedEvents(): array
            {
                return ['SomeEvent' => 42]; // @phpstan-ignore-line
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid subscription config/');

        $this->loader->load($this->dispatcher, $subscriber);
    }

    public function test_invalid_config_array_without_zero_key_throws(): void
    {
        $subscriber = new class implements EventSubscriberInterface {
            public function getSubscribedEvents(): array
            {
                return ['SomeEvent' => [1 => 'wrongKey']]; // @phpstan-ignore-line
            }
        };

        $this->expectException(InvalidArgumentException::class);

        $this->loader->load($this->dispatcher, $subscriber);
    }

    public function test_invalid_config_empty_array_throws(): void
    {
        $subscriber = new class implements EventSubscriberInterface {
            public function getSubscribedEvents(): array
            {
                return ['SomeEvent' => []]; // @phpstan-ignore-line
            }
        };

        $this->expectException(InvalidArgumentException::class);

        $this->loader->load($this->dispatcher, $subscriber);
    }

    // ── Line 29: CastString on $eventClass ────────────────────────────────────

    public function test_event_class_string_used_as_subscription_key(): void
    {
        $subscriber = new class implements EventSubscriberInterface {
            public function getSubscribedEvents(): array
            {
                return [\stdClass::class => 'handle'];
            }

            public function handle(object $event): void {}
        };

        $this->loader->load($this->dispatcher, $subscriber);

        self::assertArrayHasKey(\stdClass::class, $this->dispatcher->subscriptions);
    }

    // ── Line 34: Throw_ when method doesn't exist ─────────────────────────────

    public function test_nonexistent_method_throws(): void
    {
        $subscriber = new class implements EventSubscriberInterface {
            public function getSubscribedEvents(): array
            {
                return ['SomeEvent' => 'nonExistentMethod'];
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no method/i');

        $this->loader->load($this->dispatcher, $subscriber);
    }

    public function test_nonexistent_method_in_array_config_throws(): void
    {
        $subscriber = new class implements EventSubscriberInterface {
            public function getSubscribedEvents(): array
            {
                return ['SomeEvent' => ['missingMethod', 0]];
            }
        };

        $this->expectException(InvalidArgumentException::class);

        $this->loader->load($this->dispatcher, $subscriber);
    }

    // ── Multiple events loaded ────────────────────────────────────────────────

    public function test_multiple_events_all_subscribed(): void
    {
        $subscriber = new class implements EventSubscriberInterface {
            public function getSubscribedEvents(): array
            {
                return [
                    'EventA' => 'onA',
                    'EventB' => ['onB', 10],
                ];
            }

            public function onA(object $event): void {}

            public function onB(object $event): void {}
        };

        $this->loader->load($this->dispatcher, $subscriber);

        self::assertArrayHasKey('EventA', $this->dispatcher->subscriptions);
        self::assertArrayHasKey('EventB', $this->dispatcher->subscriptions);
    }
}
