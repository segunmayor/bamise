<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\RateLimit;

use Bamise\Contract\CachePortInterface;
use Bamise\Contract\Security\RateLimiterPortInterface;

final class CacheRateLimiter implements RateLimiterPortInterface
{
    private const string CACHE_PREFIX = 'ratelimit:';

    public function __construct(
        private readonly CachePortInterface $cache,
        private readonly RateLimitConfig $config,
    ) {
    }

    #[\Override]
    public function attempt(string $key): bool
    {
        $cacheKey = self::CACHE_PREFIX . $key;
        $now = time();
        /** @var array{count: int, window_start: int}|null $window */
        $window = $this->cache->get($cacheKey);

        if ($window === null || ($now - $window['window_start']) >= $this->config->windowSeconds) {
            $this->cache->set($cacheKey, ['count' => 1, 'window_start' => $now], $this->config->windowSeconds);

            return true;
        }

        if ($window['count'] >= $this->config->maxAttempts) {
            return false;
        }

        $window['count']++;
        $this->cache->set($cacheKey, $window, $this->config->windowSeconds);

        return true;
    }

    #[\Override]
    public function remaining(string $key): int
    {
        $cacheKey = self::CACHE_PREFIX . $key;
        $now = time();
        /** @var array{count: int, window_start: int}|null $window */
        $window = $this->cache->get($cacheKey);

        if ($window === null || ($now - $window['window_start']) >= $this->config->windowSeconds) {
            return $this->config->maxAttempts;
        }

        return max(0, $this->config->maxAttempts - $window['count']);
    }
}
