<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Cache;

use Bamise\Infrastructure\Cache\InMemoryCache;
use PHPUnit\Framework\TestCase;

final class InMemoryCacheTest extends TestCase
{
    public function test_returns_null_for_missing_key(): void
    {
        self::assertNull((new InMemoryCache())->get('missing'));
    }

    public function test_stores_and_retrieves_value(): void
    {
        $cache = new InMemoryCache();
        $cache->set('key', 'value');

        self::assertSame('value', $cache->get('key'));
    }

    public function test_stores_array_value(): void
    {
        $cache = new InMemoryCache();
        $cache->set('data', ['foo' => 'bar']);

        self::assertSame(['foo' => 'bar'], $cache->get('data'));
    }

    public function test_delete_removes_key_and_returns_true(): void
    {
        $cache = new InMemoryCache();
        $cache->set('key', 'value');

        self::assertTrue($cache->delete('key'));
        self::assertNull($cache->get('key'));
    }

    public function test_delete_returns_false_for_absent_key(): void
    {
        self::assertFalse((new InMemoryCache())->delete('missing'));
    }

    public function test_clear_removes_all_entries(): void
    {
        $cache = new InMemoryCache();
        $cache->set('a', 1);
        $cache->set('b', 2);
        $cache->clear();

        self::assertNull($cache->get('a'));
        self::assertNull($cache->get('b'));
    }

    public function test_returns_null_after_ttl_expires(): void
    {
        $cache = new InMemoryCache();
        $cache->set('key', 'value', ttl: -1);

        self::assertNull($cache->get('key'));
    }

    public function test_returns_value_within_ttl(): void
    {
        $cache = new InMemoryCache();
        $cache->set('key', 'alive', ttl: 3600);

        self::assertSame('alive', $cache->get('key'));
    }

    public function test_null_ttl_never_expires(): void
    {
        $cache = new InMemoryCache();
        $cache->set('key', 'persistent', ttl: null);

        self::assertSame('persistent', $cache->get('key'));
    }

    public function test_overwrite_existing_key(): void
    {
        $cache = new InMemoryCache();
        $cache->set('key', 'first');
        $cache->set('key', 'second');

        self::assertSame('second', $cache->get('key'));
    }
}
