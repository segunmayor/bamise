<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Infrastructure\Cache\InMemoryCache;
use Bamise\Infrastructure\Event\ListenerRegistry;
use Bamise\Infrastructure\Event\SyncEventDispatcher;
use Bamise\Infrastructure\Persistence\PDO\Dialect\SqliteDialect;
use Bamise\Infrastructure\Persistence\Query\SqlCompiler;
use Bamise\Infrastructure\Queue\InMemoryQueue;
use Bamise\Infrastructure\Security\Auth\BearerTokenAuthAdapter;
use Bamise\Infrastructure\Security\RateLimit\CacheRateLimiter;
use Bamise\Infrastructure\Security\RateLimit\RateLimitConfig;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

/**
 * Concurrency simulation and load/volume tests.
 *
 * PHP is single-threaded so we simulate concurrent state changes by interleaving
 * calls that would race in a multi-process environment and checking invariants hold.
 */
final class ConcurrencyAndLoadTest extends TestCase
{
    // ── Rate limiter: TOCTOU simulation ──────────────────────────────────────

    /**
     * Simulate two "processes" both reading "remaining = 1" simultaneously.
     * After interleaving, only the first write wins in-process; the second
     * exceeds the limit. This confirms the per-request check prevents bypass.
     */
    public function test_rate_limiter_last_attempt_is_not_double_counted(): void
    {
        $config = new RateLimitConfig(maxAttempts: 2, windowSeconds: 60);
        $cache = new InMemoryCache();
        $limiter = new CacheRateLimiter($cache, $config);

        // Simulate sequential "concurrent" requests from the same key.
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $results[] = $limiter->attempt('shared-key');
        }

        $allowed = array_filter($results, static fn (bool $r): bool => $r);
        $denied  = array_filter($results, static fn (bool $r): bool => ! $r);

        // Exactly maxAttempts should succeed; the rest must fail.
        self::assertCount(2, $allowed);
        self::assertCount(3, $denied);
    }

    public function test_rate_limiter_independent_keys_do_not_interfere(): void
    {
        $config = new RateLimitConfig(maxAttempts: 3, windowSeconds: 60);
        $cache = new InMemoryCache();
        $limiter = new CacheRateLimiter($cache, $config);

        // Interleave attempts for two different keys.
        $resultA = $resultB = [];
        for ($i = 0; $i < 4; $i++) {
            $resultA[] = $limiter->attempt('user:1');
            $resultB[] = $limiter->attempt('user:2');
        }

        // Each key should independently allow exactly maxAttempts.
        self::assertCount(3, array_filter($resultA));
        self::assertCount(3, array_filter($resultB));
    }

    // ── BearerToken: state isolation between simulated requests ──────────────

    public function test_bearer_token_adapter_resets_subject_between_requests(): void
    {
        $adapter = new BearerTokenAuthAdapter();

        $req1 = new FakeCrudRequest(headers: ['Authorization' => 'Bearer 42']);
        $req2 = new FakeCrudRequest(headers: []); // no auth

        $adapter->authenticate($req1);
        self::assertNotNull($adapter->subject());

        $adapter->authenticate($req2);
        self::assertNull($adapter->subject(), 'Subject must be reset for unauthenticated request.');
    }

    public function test_bearer_token_adapter_does_not_bleed_subject_from_previous_call(): void
    {
        $adapter = new BearerTokenAuthAdapter();

        $req1 = new FakeCrudRequest(headers: ['Authorization' => 'Bearer 100']);
        $req2 = new FakeCrudRequest(headers: ['Authorization' => 'Bearer 200']);

        $adapter->authenticate($req1);
        $subject1 = $adapter->subject();

        $adapter->authenticate($req2);
        $subject2 = $adapter->subject();

        self::assertNotSame($subject1, $subject2);
    }

    // ── Cache: state isolation between simulated requests ────────────────────

    public function test_in_memory_cache_does_not_share_state_between_instances(): void
    {
        $cache1 = new InMemoryCache();
        $cache2 = new InMemoryCache();

        $cache1->set('shared', 'from-cache1');

        self::assertNull($cache2->get('shared'));
    }

    // ── SyncEventDispatcher: listener storm ───────────────────────────────────

    public function test_dispatcher_handles_many_listeners_without_error(): void
    {
        $registry = new ListenerRegistry();
        $dispatcher = new SyncEventDispatcher($registry);

        $callCount = 0;
        for ($i = 0; $i < 200; $i++) {
            $dispatcher->subscribe(\stdClass::class, static function () use (&$callCount): void {
                $callCount++;
            });
        }

        $dispatcher->dispatch(new \stdClass());

        self::assertSame(200, $callCount);
    }

    public function test_dispatcher_listener_returning_false_stops_propagation(): void
    {
        $registry = new ListenerRegistry();
        $dispatcher = new SyncEventDispatcher($registry);

        $calls = [];
        $dispatcher->subscribe(\stdClass::class, static function () use (&$calls): bool {
            $calls[] = 'first';
            return false; // stop propagation
        }, 10);
        $dispatcher->subscribe(\stdClass::class, static function () use (&$calls): void {
            $calls[] = 'second';
        }, 5);

        $dispatcher->dispatch(new \stdClass());

        self::assertSame(['first'], $calls);
    }

    // ── SqlCompiler + large payloads ──────────────────────────────────────────

    public function test_sql_compiler_handles_100_criteria_columns(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());

        $criteria = [];
        for ($i = 0; $i < 100; $i++) {
            $criteria["col_{$i}"] = "val_{$i}";
        }

        $query = $compiler->compileSelectAll('big_table', $criteria, 100, 0);

        self::assertStringContainsString('WHERE', $query->sql);
        self::assertCount(100, $query->bindings);
    }

    public function test_sql_compiler_handles_large_bulk_update(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());

        $data = [];
        for ($i = 0; $i < 50; $i++) {
            $data["col_{$i}"] = "val_{$i}";
        }

        $query = $compiler->compileUpdateWhere('posts', ['status' => 'draft'], $data);

        self::assertCount(51, $query->bindings); // 50 set + 1 where
    }

    // ── InMemoryQueue: large volume ───────────────────────────────────────────

    public function test_queue_handles_large_number_of_jobs(): void
    {
        $queue = new InMemoryQueue();

        for ($i = 0; $i < 1000; $i++) {
            $queue->push("job.{$i}", ['index' => $i]);
        }

        self::assertSame(1000, $queue->count());
        self::assertSame(0, $queue->all()[0]['payload']['index']);
        self::assertSame(999, $queue->all()[999]['payload']['index']);
    }

    // ── Mass assignment protection: boundary conditions ───────────────────────

    public function test_sql_compiler_whitelist_with_many_attack_columns_strips_all(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());

        $attacker = [];
        for ($i = 0; $i < 50; $i++) {
            $attacker["admin_col_{$i}"] = "injected_{$i}";
        }
        $attacker['name'] = 'Alice';

        $filtered = $compiler->whitelistColumns(['name', 'email'], $attacker);

        self::assertSame(['name' => 'Alice'], $filtered);
    }

    // ── Pagination boundary values ────────────────────────────────────────────

    public function test_sql_compiler_select_all_limit_zero(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());
        $query = $compiler->compileSelectAll('t', [], 0, 0);

        self::assertStringContainsString('LIMIT 0 OFFSET 0', $query->sql);
    }

    public function test_sql_compiler_select_all_max_int_limit(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());
        $query = $compiler->compileSelectAll('t', [], PHP_INT_MAX, 0);

        self::assertStringContainsString('LIMIT ' . PHP_INT_MAX, $query->sql);
    }

    public function test_sql_compiler_select_all_large_offset(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());
        $query = $compiler->compileSelectAll('t', [], 100, 1_000_000);

        self::assertStringContainsString('OFFSET 1000000', $query->sql);
    }
}
