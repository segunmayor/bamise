<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Persistence\Repository;

use Bamise\Contract\Crud\ResourceDefinitionInterface;
use Bamise\Contract\Persistence\RepositoryInterface;
use InvalidArgumentException;

final class InfrastructureRepositoryRegistry
{
    /** @var array<string, RepositoryInterface> */
    private array $repositories = [];

    public function __construct(
        private readonly PdoRepositoryFactory $factory,
    ) {
    }

    public function register(string $resourceName, ResourceDefinitionInterface $definition): void
    {
        $this->repositories[$resourceName] = $this->factory->for($definition);
    }

    public function registerRepository(string $resourceName, RepositoryInterface $repository): void
    {
        $this->repositories[$resourceName] = $repository;
    }

    public function get(string $resourceName): RepositoryInterface
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
