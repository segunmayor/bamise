<?php

declare(strict_types=1);

namespace Bamise\Contract;

interface CachePortInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, ?int $ttl = null): void;

    public function delete(string $key): bool;
}
