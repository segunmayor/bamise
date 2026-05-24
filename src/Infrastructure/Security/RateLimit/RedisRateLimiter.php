<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\RateLimit;

use Bamise\Contract\Security\RedisClientInterface;
use Bamise\Contract\Security\RateLimiterPortInterface;

/**
 * Atomic sliding-window rate limiter backed by Redis.
 *
 * Replaces the non-atomic CacheRateLimiter. Both attempt() and remaining()
 * execute single Lua scripts so no read-check-write race can occur between
 * concurrent PHP workers.
 *
 * Algorithm: sorted-set (ZSET) sliding window.
 *   - Score  = request timestamp in milliseconds.
 *   - Member = timestamp:random-hex (unique per request).
 *   - Window = entries whose score >= (now - windowMs).
 *
 * Adapting to a concrete Redis client:
 *   phpredis:  fn($script, $keys, $args) => $redis->eval($script, array_merge($keys, $args), count($keys))
 *   predis:    fn($script, $keys, $args) => $client->eval($script, count($keys), ...array_merge($keys, $args))
 */
final class RedisRateLimiter implements RateLimiterPortInterface
{
    private const string KEY_PREFIX = 'rl:';

    /**
     * Atomically checks the sliding window and, if within the limit, records
     * the current request.  Returns {1, remaining} on allow, {0, 0} on deny.
     */
    private const string ATTEMPT_SCRIPT = <<<'LUA'
        local key    = KEYS[1]
        local now    = tonumber(ARGV[1])
        local win    = tonumber(ARGV[2])
        local limit  = tonumber(ARGV[3])
        local member = ARGV[4]
        redis.call('ZREMRANGEBYSCORE', key, '-inf', now - win)
        local count = tonumber(redis.call('ZCARD', key))
        if count >= limit then
            return {0, 0}
        end
        redis.call('ZADD', key, now, member)
        redis.call('PEXPIRE', key, win)
        return {1, limit - count - 1}
        LUA;

    /**
     * Non-destructive read of remaining capacity in the current window.
     * Uses ZCOUNT (range query) so it does not mutate the set.
     */
    private const string REMAINING_SCRIPT = <<<'LUA'
        local key   = KEYS[1]
        local now   = tonumber(ARGV[1])
        local win   = tonumber(ARGV[2])
        local limit = tonumber(ARGV[3])
        local count = tonumber(redis.call('ZCOUNT', key, now - win, '+inf'))
        local rem   = limit - count
        if rem < 0 then rem = 0 end
        return rem
        LUA;

    /** @var \Closure(): int */
    private readonly \Closure $clock;

    /**
     * @param (\Closure(): int)|null $clock Millisecond clock; defaults to system microtime.
     *                                      Inject a fixed clock in tests to control timestamps.
     */
    public function __construct(
        private readonly RedisClientInterface $redis,
        private readonly RateLimitConfig $config,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn(): int => (int) (microtime(true) * 1_000);
    }

    #[\Override]
    public function attempt(string $key): bool
    {
        $cacheKey = self::KEY_PREFIX . $key;
        $nowMs    = ($this->clock)();
        $winMs    = $this->config->windowSeconds * 1_000;
        $member   = $nowMs . ':' . bin2hex(random_bytes(8));

        $raw = $this->redis->evalScript(
            self::ATTEMPT_SCRIPT,
            [$cacheKey],
            [$nowMs, $winMs, $this->config->maxAttempts, $member],
        );

        return is_array($raw) && ($raw[0] ?? 0) === 1;
    }

    #[\Override]
    public function remaining(string $key): int
    {
        $cacheKey = self::KEY_PREFIX . $key;
        $nowMs    = ($this->clock)();
        $winMs    = $this->config->windowSeconds * 1_000;

        $raw = $this->redis->evalScript(
            self::REMAINING_SCRIPT,
            [$cacheKey],
            [$nowMs, $winMs, $this->config->maxAttempts],
        );

        return max(0, is_int($raw) ? $raw : 0);
    }
}
