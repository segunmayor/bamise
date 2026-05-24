<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Infrastructure\Security\RateLimit\RateLimitConfig;
use Bamise\Infrastructure\Security\RateLimit\RedisRateLimiter;
use Bamise\Tests\Fixtures\InMemoryRedisClient;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for RedisRateLimiter using an injectable clock.
 *
 * Kills escaped mutants:
 *
 * Line 85 (attempt winMs * 1_000):
 *   - DecrementInteger (* 999): entry at t=0 would be prematurely evicted at t=999
 *     if winMs=999 → attempt wrongly allowed. Test asserts denied at t=999.
 *   - IncrementInteger (* 1_001): entry at t=0 NOT evicted at t=1000 if winMs=1001
 *     → attempt wrongly denied. Test asserts allowed at t=1000.
 *
 * Line 102 (remaining winMs * 1_000):
 *   - DecrementInteger (* 999): entry at t=0 evicted at t=1000 if winMs=999 → remaining=1.
 *     Test asserts remaining=0 at t=1000 (entry still in window with winMs=1000).
 *   - IncrementInteger (* 1_001): entry at t=0 NOT evicted at t=1001 if winMs=1001 → remaining=0.
 *     Test asserts remaining=1 at t=1001 (entry expired with winMs=1000).
 *
 * Line 86 (member ConcatOperandRemoval removing random hex):
 *   - Without random bytes, member = "$nowMs:" for all calls at same clock value.
 *     ZADD overwrites the same ZSET entry → count stays 1 → bypass.
 *     Fixed clock at t=0 guarantees same $nowMs for all calls; test asserts
 *     the (limit+1)-th request is denied.
 */
final class RedisRateLimiterWindowBoundaryTest extends TestCase
{
    // ── attempt() window boundary: * 999 mutant ──────────────────────────────

    /**
     * Entry added at t=0, window=1000ms.  At t=999 the entry is still live.
     *
     * Kill: * 999 mutant → winMs=999 → cutoff=999-999=0 → entry score(0) NOT > 0
     *       → entry evicted → attempt allowed. Original correctly denies (entry alive).
     */
    public function test_attempt_denied_999ms_into_one_second_window(): void
    {
        $ms     = 0;
        $redis  = new InMemoryRedisClient();
        $clock  = static function () use (&$ms): int { return $ms; };
        $config = new RateLimitConfig(maxAttempts: 1, windowSeconds: 1);
        $limit  = new RedisRateLimiter($redis, $config, $clock);

        $ms = 0;
        self::assertTrue($limit->attempt('k'), 'First attempt at t=0 must be allowed.');

        $ms = 999;
        self::assertFalse($limit->attempt('k'), 'At t=999ms entry still in 1000ms window — must be denied.');
    }

    // ── attempt() window boundary: * 1001 mutant ─────────────────────────────

    /**
     * Entry added at t=0, window=1000ms.  At t=1000 the entry has expired
     * (cutoff = 1000-1000 = 0; entry score 0 is NOT > 0 → evicted).
     *
     * Kill: * 1001 mutant → winMs=1001 → cutoff=1000-1001=-1 → entry score(0) > -1
     *       → entry NOT evicted → attempt denied. Original correctly allows (entry gone).
     */
    public function test_attempt_allowed_at_1000ms_when_window_expired(): void
    {
        $ms     = 0;
        $redis  = new InMemoryRedisClient();
        $clock  = static function () use (&$ms): int { return $ms; };
        $config = new RateLimitConfig(maxAttempts: 1, windowSeconds: 1);
        $limit  = new RedisRateLimiter($redis, $config, $clock);

        $ms = 0;
        self::assertTrue($limit->attempt('k'));

        $ms = 1_000;
        self::assertTrue($limit->attempt('k'), 'At t=1000ms the 1000ms window has expired — must be allowed.');
    }

    // ── remaining() window boundary: * 999 mutant ───────────────────────────

    /**
     * Entry at t=0, remaining() queried at t=1000 with window=1000ms.
     *
     * Original: ZCOUNT cutoff=1000-1000=0, entry(score=0) >= 0 → counted → remaining=0.
     * Kill: * 999 mutant → cutoff=1000-999=1, entry(score=0) < 1 → NOT counted → remaining=1.
     */
    public function test_remaining_zero_at_exact_window_expiry(): void
    {
        $ms     = 0;
        $redis  = new InMemoryRedisClient();
        $clock  = static function () use (&$ms): int { return $ms; };
        $config = new RateLimitConfig(maxAttempts: 1, windowSeconds: 1);
        $limit  = new RedisRateLimiter($redis, $config, $clock);

        $ms = 0;
        $limit->attempt('k');

        $ms = 1_000;
        self::assertSame(0, $limit->remaining('k'), 'Entry at t=0 is on the window boundary at t=1000ms — still counted, remaining=0.');
    }

    // ── remaining() window boundary: * 1001 mutant ──────────────────────────

    /**
     * Entry at t=0, remaining() queried at t=1001 with window=1000ms.
     *
     * Original: cutoff=1001-1000=1, entry(score=0) < 1 → NOT counted → remaining=1.
     * Kill: * 1001 mutant → cutoff=1001-1001=0, entry(score=0) >= 0 → counted → remaining=0.
     */
    public function test_remaining_one_at_one_ms_past_window_expiry(): void
    {
        $ms     = 0;
        $redis  = new InMemoryRedisClient();
        $clock  = static function () use (&$ms): int { return $ms; };
        $config = new RateLimitConfig(maxAttempts: 1, windowSeconds: 1);
        $limit  = new RedisRateLimiter($redis, $config, $clock);

        $ms = 0;
        $limit->attempt('k');

        $ms = 1_001;
        self::assertSame(1, $limit->remaining('k'), 'Entry at t=0 expired 1ms ago at t=1001ms — window reset, remaining=1.');
    }

    // ── member uniqueness at same timestamp: ConcatOperandRemoval kill ───────

    /**
     * With a fixed clock all calls share the same $nowMs.
     *
     * Original: member = "$nowMs:<random16hex>" — unique per request → ZADD grows ZSET → limit enforced.
     * Kill: ConcatOperandRemoval mutant removes bin2hex(random_bytes(8)) → member = "$nowMs:" always
     *       → ZADD overwrites same entry → ZSET stays at 1 entry → bypass (all requests allowed).
     */
    public function test_limit_enforced_when_all_requests_share_same_millisecond(): void
    {
        $redis  = new InMemoryRedisClient();
        $fixed  = static fn (): int => 1_000_000; // every call returns the same ms
        $config = new RateLimitConfig(maxAttempts: 5, windowSeconds: 60);
        $limit  = new RedisRateLimiter($redis, $config, $fixed);

        $allowed = 0;
        for ($i = 0; $i < 10; $i++) {
            if ($limit->attempt('burst')) {
                $allowed++;
            }
        }

        self::assertSame(5, $allowed, 'Exactly 5 of 10 same-millisecond requests must be allowed — no bypass via non-unique member.');
        self::assertSame(0, $limit->remaining('burst'));
    }
}
