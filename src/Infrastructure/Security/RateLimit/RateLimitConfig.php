<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\RateLimit;

readonly class RateLimitConfig
{
    public function __construct(
        public int $maxAttempts = 60,
        public int $windowSeconds = 60,
    ) {
    }
}
