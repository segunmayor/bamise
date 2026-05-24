<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Infrastructure\Cache\InMemoryCache;
use Bamise\Infrastructure\Security\RateLimit\CacheRateLimiter;
use Bamise\Infrastructure\Security\RateLimit\RateLimitConfig;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for CacheRateLimiter boundary conditions.
 *
 * Kills escaped mutants:
 *   Line 28: GreaterThanOrEqualTo — `>=` changed to `>` in the window-expiry check.
 *   Line 52: GreaterThanOrEqualTo — same check in remaining().
 *
 * Strategy: pre-seed the cache with a window whose start equals time() so that
 * elapsed = time() - window_start = 0 = windowSeconds (with windowSeconds=0).
 *
 *   Original (>=): 0 >= 0 → true → window is treated as expired → fresh budget returned.
 *   Mutant    (>):  0 > 0 → false → window NOT treated as expired → old count used.
 *
 * Using windowSeconds=0 makes elapsed always equal windowSeconds the instant
 * the window is started, so the check fires on the very next attempt().
 */
final class CacheRateLimiterBoundaryTest extends TestCase
{
    // ── Line 28: GreaterThanOrEqualTo in attempt() ───────────────────────────

    public function test_attempt_resets_window_when_elapsed_equals_window_seconds(): void
    {
        $cache = new InMemoryCache();

        // Pre-seed an exhausted window that started right now (elapsed = 0 = windowSeconds).
        $cache->set('ratelimit:k', ['count' => 3, 'window_start' => time()], null);

        $limiter = new CacheRateLimiter($cache, new RateLimitConfig(maxAttempts: 3, windowSeconds: 0));

        // Original (>=): elapsed(0) >= windowSeconds(0) → true → reset → allow (count resets to 1).
        // Mutant   (>):  elapsed(0) >  windowSeconds(0) → false → do NOT reset → block (count was 3 ≥ 3).
        self::assertTrue(
            $limiter->attempt('k'),
            'When elapsed equals windowSeconds the window must reset (>= not >).',
        );
    }

    public function test_attempt_does_not_reset_when_elapsed_is_less_than_window(): void
    {
        $cache = new InMemoryCache();
        // Fresh window started just now, windowSeconds large enough to not expire.
        $cache->set('ratelimit:k', ['count' => 1, 'window_start' => time()], null);

        $limiter = new CacheRateLimiter($cache, new RateLimitConfig(maxAttempts: 3, windowSeconds: 60));

        // elapsed ≈ 0 < 60 → window still active → count increments (not reset).
        self::assertTrue($limiter->attempt('k'));
        self::assertSame(1, $limiter->remaining('k')); // 3 - 2 = 1
    }

    // ── Line 52: GreaterThanOrEqualTo in remaining() ─────────────────────────

    public function test_remaining_returns_max_when_elapsed_equals_window_seconds(): void
    {
        $cache = new InMemoryCache();

        // Pre-seed an exhausted window (count = maxAttempts) started exactly now.
        $cache->set('ratelimit:k', ['count' => 5, 'window_start' => time()], null);

        $limiter = new CacheRateLimiter($cache, new RateLimitConfig(maxAttempts: 5, windowSeconds: 0));

        // Original (>=): elapsed(0) >= windowSeconds(0) → true → return maxAttempts (5).
        // Mutant   (>):  elapsed(0) >  windowSeconds(0) → false → return max(0, 5-5) = 0.
        self::assertSame(
            5,
            $limiter->remaining('k'),
            'remaining() must report full budget when elapsed equals windowSeconds (window reset).',
        );
    }

    // ── Line 56: DecrementInteger — max(0,...) not max(-1,...) ───────────────

    public function test_remaining_returns_zero_not_negative_when_count_exceeds_max(): void
    {
        $cache = new InMemoryCache();

        // Pre-seed with count EXCEEDING maxAttempts (e.g. concurrent writes).
        // maxAttempts - count = 3 - 5 = -2; max(0, -2) = 0, max(-1, -2) = -1.
        $cache->set('ratelimit:k', ['count' => 5, 'window_start' => time() - 1], null);

        $limiter = new CacheRateLimiter($cache, new RateLimitConfig(maxAttempts: 3, windowSeconds: 60));

        self::assertSame(
            0,
            $limiter->remaining('k'),
            'remaining() must clamp to 0 even when count exceeds maxAttempts.',
        );
    }
}
