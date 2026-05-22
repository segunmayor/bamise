<?php

declare(strict_types=1);

namespace Bamise\Application\Registry;

use Bamise\Contract\Crud\ResourceDefinitionInterface;
use InvalidArgumentException;

final class ResourceRegistry
{
    /** @var array<string, ResourceDefinitionInterface> */
    private array $resources = [];

    /**
     * @param iterable<string, ResourceDefinitionInterface> $resources
     */
    public function __construct(iterable $resources = [])
    {
        foreach ($resources as $name => $definition) {
            $this->register($name, $definition);
        }
    }

    public function register(string $name, ResourceDefinitionInterface $definition): void
    {
        $this->resources[$name] = $definition;
    }

    public function get(string $name): ResourceDefinitionInterface
    {
        if (! isset($this->resources[$name])) {
            throw new InvalidArgumentException(
                sprintf('Resource "%s" is not registered.', $name),
            );
        }

        return $this->resources[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->resources[$name]);
    }

    /**
     * @return array<string, ResourceDefinitionInterface>
     */
    public function all(): array
    {
        return $this->resources;
    }
}
