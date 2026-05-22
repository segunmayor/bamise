<?php

declare(strict_types=1);

namespace Bamise\Contract\Security;

interface RateLimiterPortInterface
{
    public function attempt(string $key): bool;

    public function remaining(string $key): int;
}
