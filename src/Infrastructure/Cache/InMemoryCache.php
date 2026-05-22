<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Cache;

use Bamise\Contract\CachePortInterface;

/**
 * Process-local cache for tests and development. Not shared across requests in PHP-FPM workers.
 */
final class InMemoryCache implements CachePortInterface
{
    /** @var array<string, array{value: mixed, expires: ?int}> */
    private array $store = [];

    public function get(string $key): mixed
    {
        if (! isset($this->store[$key])) {
            return null;
        }

        $entry = $this->store[$key];
        if ($entry['expires'] !== null && $entry['expires'] < time()) {
            unset($this->store[$key]);

            return null;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->store[$key] = [
            'value' => $value,
            'expires' => $ttl !== null ? time() + $ttl : null,
        ];
    }

    public function delete(string $key): bool
    {
        if (! isset($this->store[$key])) {
            return false;
        }

        unset($this->store[$key]);

        return true;
    }

    public function clear(): void
    {
        $this->store = [];
    }
}
