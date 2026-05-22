<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Infrastructure\Cache\InMemoryCache;
use Bamise\Infrastructure\Security\RateLimit\CacheRateLimiter;
use Bamise\Infrastructure\Security\RateLimit\RateLimitConfig;
use PHPUnit\Framework\TestCase;

final class CacheRateLimiterTest extends TestCase
{
    public function test_blocks_after_max_attempts(): void
    {
        $limiter = new CacheRateLimiter(
            new InMemoryCache(),
            new RateLimitConfig(maxAttempts: 2, windowSeconds: 60),
        );

        self::assertTrue($limiter->attempt('client-a'));
        self::assertTrue($limiter->attempt('client-a'));
        self::assertFalse($limiter->attempt('client-a'));
        self::assertSame(0, $limiter->remaining('client-a'));
    }

    public function test_separate_keys_are_independent(): void
    {
        $limiter = new CacheRateLimiter(
            new InMemoryCache(),
            new RateLimitConfig(maxAttempts: 1, windowSeconds: 60),
        );

        self::assertTrue($limiter->attempt('a'));
        self::assertFalse($limiter->attempt('a'));
        self::assertTrue($limiter->attempt('b'));
    }
}
