<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Infrastructure\Security\RateLimit\RateLimitConfig;
use Bamise\Infrastructure\Security\RateLimit\RedisRateLimiter;
use Bamise\Tests\Fixtures\InMemoryRedisClient;
use PHPUnit\Framework\TestCase;

/**
 * Concurrency and load tests for RedisRateLimiter.
 *
 * "Parallel" here means N sequential calls from the same process against the
 * same atomic counter, simulating the invariant a concurrent Redis EVAL
 * guarantee provides.  Since PHP is single-threaded within a process and the
 * InMemoryRedisClient mirrors the Lua sliding-window logic atomically, the
 * tests prove:
 *
 *   1. Exactly `limit` requests are admitted — no under-count, no bypass.
 *   2. All excess requests are denied — no slip-through.
 *   3. remaining() reaches 0 and never goes negative.
 *   4. Independent client keys never bleed budget into each other.
 *
 * Atomicity guarantee in production comes from Redis EVAL executing the full
 * ZREMRANGEBYSCORE → ZCARD → ZADD sequence as a single transaction.  True
 * concurrent bypass testing (multiple OS processes hammering a live Redis
 * instance) belongs in an integration/stress-test suite.
 */
final class RedisRateLimiterConcurrencyTest extends TestCase
{
    // ── No-bypass under simulated parallel load ──────────────────────────────

    /**
     * @dataProvider concurrencyScenarios
     */
    public function test_no_bypass_under_simulated_parallel_load(
        int $totalRequests,
        int $limit,
    ): void {
        $limiter = new RedisRateLimiter(
            new InMemoryRedisClient(),
            new RateLimitConfig($limit, windowSeconds: 60),
        );

        $allowed = 0;
        $denied  = 0;

        for ($i = 0; $i < $totalRequests; $i++) {
            if ($limiter->attempt('concurrent-key')) {
                $allowed++;
            } else {
                $denied++;
            }
        }

        self::assertSame(
            $limit,
            $allowed,
            "With $totalRequests requests and limit=$limit, exactly $limit must be allowed (no bypass, no under-count).",
        );
        self::assertSame(
            $totalRequests - $limit,
            $denied,
            'Every request beyond the limit must be denied.',
        );
        self::assertSame(
            0,
            $limiter->remaining('concurrent-key'),
            'Zero tokens must remain after limit exhaustion.',
        );
    }

    /** @return array<string, array{int, int}> */
    public static function concurrencyScenarios(): array
    {
        return [
            '100-requests  / limit-10'  => [100,   10],
            '500-requests  / limit-50'  => [500,   50],
            '1000-requests / limit-100' => [1_000, 100],
            '5000-requests / limit-500' => [5_000, 500],
        ];
    }

    // ── Exact-limit edge case ────────────────────────────────────────────────

    public function test_all_requests_allowed_when_count_equals_limit(): void
    {
        $limit   = 1_000;
        $limiter = new RedisRateLimiter(
            new InMemoryRedisClient(),
            new RateLimitConfig($limit, windowSeconds: 60),
        );

        $allowed = 0;
        for ($i = 0; $i < $limit; $i++) {
            if ($limiter->attempt('exact')) {
                $allowed++;
            }
        }

        self::assertSame($limit, $allowed, 'Every request must be allowed when count equals limit exactly.');
        self::assertSame(0, $limiter->remaining('exact'));
    }

    // ── remaining() never negative under excess load ─────────────────────────

    public function test_remaining_stays_zero_throughout_excess_load(): void
    {
        $limit   = 10;
        $limiter = new RedisRateLimiter(
            new InMemoryRedisClient(),
            new RateLimitConfig($limit, windowSeconds: 60),
        );

        // Exhaust budget
        for ($i = 0; $i < $limit; $i++) {
            $limiter->attempt('k');
        }

        // Fire 990 excess requests; remaining() must never dip below 0
        for ($i = 0; $i < 990; $i++) {
            $limiter->attempt('k');
            self::assertSame(
                0,
                $limiter->remaining('k'),
                "remaining() must be 0 after exhaustion (excess call $i).",
            );
        }
    }

    // ── Independent keys under interleaved load ──────────────────────────────

    public function test_100_interleaved_keys_never_bleed_budget(): void
    {
        $limit   = 50;
        $numKeys = 10;
        $limiter = new RedisRateLimiter(
            new InMemoryRedisClient(),
            new RateLimitConfig($limit, windowSeconds: 60),
        );

        // 100 requests per key × 10 keys interleaved (each key stays within budget)
        $allowed = array_fill(0, $numKeys, 0);

        for ($r = 0; $r < $numKeys * 100; $r++) {
            $k = $r % $numKeys;
            if ($limiter->attempt("client-$k")) {
                $allowed[$k]++;
            }
        }

        for ($k = 0; $k < $numKeys; $k++) {
            self::assertSame(
                $limit,
                $allowed[$k],
                "client-$k: exactly $limit of 100 interleaved requests must be allowed.",
            );
        }
    }

    // ── 500-key fan-out ──────────────────────────────────────────────────────

    public function test_500_distinct_clients_each_get_independent_budget(): void
    {
        $limit   = 5;
        $clients = 500;
        $limiter = new RedisRateLimiter(
            new InMemoryRedisClient(),
            new RateLimitConfig($limit, windowSeconds: 60),
        );

        for ($c = 0; $c < $clients; $c++) {
            $key = "user-$c";
            // Each client fires limit+1 requests
            $clientAllowed = 0;
            for ($r = 0; $r <= $limit; $r++) {
                if ($limiter->attempt($key)) {
                    $clientAllowed++;
                }
            }
            self::assertSame($limit, $clientAllowed, "user-$c must be admitted exactly $limit times.");
            self::assertSame(0, $limiter->remaining($key), "user-$c remaining must be 0.");
        }
    }

    // ── Burst: all 5000 on the same key sequentially ─────────────────────────

    public function test_5000_burst_on_single_key_admits_exactly_the_limit(): void
    {
        $limit   = 200;
        $burst   = 5_000;
        $limiter = new RedisRateLimiter(
            new InMemoryRedisClient(),
            new RateLimitConfig($limit, windowSeconds: 60),
        );

        $allowed = 0;
        for ($i = 0; $i < $burst; $i++) {
            if ($limiter->attempt('burst-key')) {
                $allowed++;
            }
        }

        self::assertSame($limit, $allowed, "$burst burst requests: exactly $limit must be admitted.");
        self::assertSame(0, $limiter->remaining('burst-key'));
    }

    // ── Denial-of-service simulation: sustained excess hammering ─────────────

    public function test_no_bypass_when_denied_requests_outnumber_limit_by_100x(): void
    {
        $limit   = 20;
        $limiter = new RedisRateLimiter(
            new InMemoryRedisClient(),
            new RateLimitConfig($limit, windowSeconds: 60),
        );

        $allowed = 0;
        for ($i = 0; $i < $limit * 100; $i++) {
            if ($limiter->attempt('dos-key')) {
                $allowed++;
            }
        }

        self::assertSame($limit, $allowed, 'Limit must hold under 100× excess hammering (no bypass).');
    }
}
