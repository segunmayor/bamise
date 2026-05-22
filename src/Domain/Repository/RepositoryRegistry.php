<?php

declare(strict_types=1);

namespace Bamise\Domain\Repository;

use InvalidArgumentException;

final class RepositoryRegistry
{
    /** @var array<string, string> */
    private array $map = [];

    public function register(string $resourceName, string $repositoryKey): void
    {
        $this->map[$resourceName] = $repositoryKey;
    }

    public function resolve(string $resourceName): string
    {
        if (! isset($this->map[$resourceName])) {
            throw new InvalidArgumentException(
                sprintf('No repository registered for resource "%s".', $resourceName),
            );
        }

        return $this->map[$resourceName];
    }

    public function has(string $resourceName): bool
    {
        return isset($this->map[$resourceName]);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->map;
    }
}
