<?php

declare(strict_types=1);

namespace Bamise\Tests\Fixtures;

use Bamise\Contract\Security\RateLimiterPortInterface;

final class FakeRateLimiterPort implements RateLimiterPortInterface
{
    public function __construct(
        private bool $allowed = true,
    ) {
    }

    public function attempt(string $key): bool
    {
        unset($key);

        return $this->allowed;
    }

    public function remaining(string $key): int
    {
        unset($key);

        return $this->allowed ? 10 : 0;
    }
}
