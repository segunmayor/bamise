<?php

declare(strict_types=1);

namespace Bamise\Domain\Model;

readonly class FieldBag
{
    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(
        private array $fields,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->fields;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->fields[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->fields);
    }

    public function count(): int
    {
        return count($this->fields);
    }
}
