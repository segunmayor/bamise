<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Infrastructure\Security\RateLimit\RateLimitConfig;
use Bamise\Infrastructure\Security\RateLimit\RedisRateLimiter;
use Bamise\Tests\Fixtures\InMemoryRedisClient;
use PHPUnit\Framework\TestCase;

final class RedisRateLimiterTest extends TestCase
{
    private InMemoryRedisClient $redis;

    protected function setUp(): void
    {
        $this->redis = new InMemoryRedisClient();
    }

    private function limiter(int $max = 5, int $window = 60): RedisRateLimiter
    {
        return new RedisRateLimiter($this->redis, new RateLimitConfig($max, $window));
    }

    // ── Basic allow / deny ───────────────────────────────────────────────────

    public function test_first_attempt_is_allowed(): void
    {
        self::assertTrue($this->limiter()->attempt('ip:1.2.3.4'));
    }

    public function test_allows_exactly_up_to_max_then_blocks(): void
    {
        $l = $this->limiter(max: 3);

        self::assertTrue($l->attempt('k'));
        self::assertTrue($l->attempt('k'));
        self::assertTrue($l->attempt('k'));
        self::assertFalse($l->attempt('k'));  // 4th — over limit
        self::assertFalse($l->attempt('k'));  // 5th — still over
    }

    public function test_limit_of_one_allows_single_request(): void
    {
        $l = $this->limiter(max: 1);

        self::assertTrue($l->attempt('u'));
        self::assertFalse($l->attempt('u'));
        self::assertFalse($l->attempt('u'));
    }

    // ── Remaining budget ─────────────────────────────────────────────────────

    public function test_remaining_equals_max_before_any_attempt(): void
    {
        self::assertSame(5, $this->limiter()->remaining('fresh'));
    }

    public function test_remaining_decrements_with_each_allowed_attempt(): void
    {
        $l = $this->limiter(max: 4);

        self::assertSame(4, $l->remaining('k'));
        $l->attempt('k');
        self::assertSame(3, $l->remaining('k'));
        $l->attempt('k');
        self::assertSame(2, $l->remaining('k'));
        $l->attempt('k');
        self::assertSame(1, $l->remaining('k'));
        $l->attempt('k');
        self::assertSame(0, $l->remaining('k'));
    }

    public function test_remaining_stays_zero_after_limit_exceeded(): void
    {
        $l = $this->limiter(max: 2);
        $l->attempt('k');
        $l->attempt('k');
        $l->attempt('k');  // denied
        $l->attempt('k');  // denied

        self::assertSame(0, $l->remaining('k'));
    }

    public function test_remaining_never_goes_negative(): void
    {
        $l = $this->limiter(max: 1);
        for ($i = 0; $i < 100; $i++) {
            $l->attempt('k');
        }
        self::assertGreaterThanOrEqual(0, $l->remaining('k'));
    }

    // ── Key isolation ────────────────────────────────────────────────────────

    public function test_separate_keys_do_not_share_budget(): void
    {
        $l = $this->limiter(max: 1);

        self::assertTrue($l->attempt('a'));
        self::assertFalse($l->attempt('a'));

        // key 'b' has its own budget — unaffected by 'a'
        self::assertTrue($l->attempt('b'));
        self::assertFalse($l->attempt('b'));
    }

    public function test_ten_independent_keys_each_exhaust_separately(): void
    {
        $l = $this->limiter(max: 2);

        for ($k = 0; $k < 10; $k++) {
            $key = "client-$k";
            self::assertTrue($l->attempt($key), "$key first");
            self::assertTrue($l->attempt($key), "$key second");
            self::assertFalse($l->attempt($key), "$key third (over limit)");
        }
    }

    // ── High volume ──────────────────────────────────────────────────────────

    public function test_high_limit_never_blocks_within_budget(): void
    {
        $l = $this->limiter(max: 1_000);
        for ($i = 0; $i < 1_000; $i++) {
            self::assertTrue($l->attempt('flood'), "attempt $i should be allowed");
        }
        self::assertFalse($l->attempt('flood'), '1001st attempt must be denied');
        self::assertSame(0, $l->remaining('flood'));
    }

    // ── Implements RateLimiterPortInterface ──────────────────────────────────

    public function test_implements_rate_limiter_port_interface(): void
    {
        self::assertInstanceOf(
            \Bamise\Contract\Security\RateLimiterPortInterface::class,
            $this->limiter(),
        );
    }

    public function test_uses_redis_client_interface(): void
    {
        self::assertInstanceOf(
            \Bamise\Contract\Security\RedisClientInterface::class,
            $this->redis,
        );
    }
}
