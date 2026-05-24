<?php

declare(strict_types=1);

namespace Bamise\Tests\Fixtures;

use Bamise\Contract\Security\RedisClientInterface;

/**
 * In-memory Redis client for testing RedisRateLimiter.
 *
 * Implements the two Lua scripts used by RedisRateLimiter in pure PHP:
 *   - ATTEMPT_SCRIPT  — detected by presence of 'ZADD' in the script body
 *   - REMAINING_SCRIPT — detected by presence of 'ZCOUNT' in the script body
 *
 * Single-threaded PHP execution makes these operations inherently atomic within
 * a test process, mirroring the atomicity guarantee Redis provides via EVAL in
 * production.  True concurrent bypass testing requires a live Redis instance and
 * multiple PHP workers.
 */
final class InMemoryRedisClient implements RedisClientInterface
{
    /** @var array<string, array<string, float>> Sorted-set store: key → [member => score] */
    private array $zsets = [];

    /** @var array<string, int> Expiry map: key → expiry timestamp in milliseconds */
    private array $expiry = [];

    /**
     * @param list<string>           $keys
     * @param list<int|string|float> $args
     */
    #[\Override]
    public function evalScript(string $script, array $keys, array $args): mixed
    {
        $this->purgeExpired((int) ($args[0] ?? 0));
        $key = $keys[0] ?? '';

        if (str_contains($script, 'ZADD')) {
            return $this->runAttempt($key, $args);
        }

        if (str_contains($script, 'ZCOUNT')) {
            return $this->runRemaining($key, $args);
        }

        throw new \InvalidArgumentException('InMemoryRedisClient: unrecognised Lua script.');
    }

    public function reset(): void
    {
        $this->zsets = [];
        $this->expiry = [];
    }

    /**
     * PHP equivalent of ATTEMPT_SCRIPT.
     *
     * @param list<int|string|float> $args [nowMs, winMs, limit, member]
     * @return array{int, int}
     */
    private function runAttempt(string $key, array $args): array
    {
        $nowMs  = (int) ($args[0] ?? 0);
        $winMs  = (int) ($args[1] ?? 0);
        $limit  = (int) ($args[2] ?? 0);
        $member = (string) ($args[3] ?? '');
        $cutoff = $nowMs - $winMs;

        // ZREMRANGEBYSCORE '-inf' cutoff — evict expired entries
        if (isset($this->zsets[$key])) {
            $this->zsets[$key] = array_filter(
                $this->zsets[$key],
                static fn (float $score): bool => $score > $cutoff,
            );
        }

        $count = count($this->zsets[$key] ?? []);

        if ($count >= $limit) {
            return [0, 0];
        }

        // ZADD key nowMs member
        $this->zsets[$key][$member] = (float) $nowMs;

        // PEXPIRE key winMs — use PHP_INT_MAX so TTL-based purge never fires in tests;
        // ZREMRANGEBYSCORE in runAttempt is the authoritative eviction mechanism.
        $this->expiry[$key] = PHP_INT_MAX;

        return [1, $limit - $count - 1];
    }

    /**
     * PHP equivalent of REMAINING_SCRIPT.
     *
     * @param list<int|string|float> $args [nowMs, winMs, limit]
     */
    private function runRemaining(string $key, array $args): int
    {
        $nowMs  = (int) ($args[0] ?? 0);
        $winMs  = (int) ($args[1] ?? 0);
        $limit  = (int) ($args[2] ?? 0);
        $cutoff = $nowMs - $winMs;

        // ZCOUNT key cutoff +inf (non-destructive count of entries in window)
        $count = 0;
        foreach ($this->zsets[$key] ?? [] as $score) {
            if ($score >= $cutoff) {
                $count++;
            }
        }

        return max(0, $limit - $count);
    }

    private function purgeExpired(int $nowMs): void
    {
        foreach ($this->expiry as $key => $expiryMs) {
            if ($nowMs > $expiryMs) {
                unset($this->zsets[$key], $this->expiry[$key]);
            }
        }
    }
}
