<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Event;

use Bamise\Infrastructure\Event\PrioritizedListener;
use PHPUnit\Framework\TestCase;

final class PrioritizedListenerTest extends TestCase
{
    public function test_stores_listener_priority_and_async_flag(): void
    {
        $fn = static fn (object $e): bool => true;
        $entry = new PrioritizedListener($fn, 10, true);

        self::assertSame($fn, $entry->listener);
        self::assertSame(10, $entry->priority);
        self::assertTrue($entry->async);
    }

    public function test_default_priority_is_zero(): void
    {
        $entry = new PrioritizedListener(static fn () => null);

        self::assertSame(0, $entry->priority);
    }

    public function test_default_async_is_false(): void
    {
        $entry = new PrioritizedListener(static fn () => null);

        self::assertFalse($entry->async);
    }

    public function test_negative_priority_is_stored_correctly(): void
    {
        $entry = new PrioritizedListener(static fn () => null, -100);

        self::assertSame(-100, $entry->priority);
    }
}
