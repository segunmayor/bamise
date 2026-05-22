<?php

declare(strict_types=1);

namespace Bamise\Application\Registry;

use Bamise\Contract\Persistence\RepositoryInterface;
use InvalidArgumentException;

final class RepositoryResolver
{
    /** @var array<string, RepositoryInterface> */
    private array $repositories;

    /**
     * @param array<string, RepositoryInterface> $repositories
     */
    public function __construct(array $repositories = [])
    {
        $this->repositories = $repositories;
    }

    public function register(string $resourceName, RepositoryInterface $repository): void
    {
        $this->repositories[$resourceName] = $repository;
    }

    public function for(string $resourceName): RepositoryInterface
    {
        if (! isset($this->repositories[$resourceName])) {
            throw new InvalidArgumentException(
                sprintf('No repository registered for resource "%s".', $resourceName),
            );
        }

        return $this->repositories[$resourceName];
    }

    public function has(string $resourceName): bool
    {
        return isset($this->repositories[$resourceName]);
    }

    /**
     * @return array<string, RepositoryInterface>
     */
    public function all(): array
    {
        return $this->repositories;
    }
}
