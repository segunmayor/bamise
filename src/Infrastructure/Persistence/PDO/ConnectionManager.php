<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Persistence\PDO;

use Bamise\Contract\Persistence\ConnectionInterface;
use InvalidArgumentException;

final class ConnectionManager
{
    /** @var array<string, ConnectionInterface> */
    private array $connections = [];

    private string $defaultName = 'default';

    public function register(string $name, ConnectionInterface $connection): void
    {
        $this->connections[$name] = $connection;
    }

    public function get(?string $name = null): ConnectionInterface
    {
        $name ??= $this->defaultName;

        if (! isset($this->connections[$name])) {
            throw new InvalidArgumentException(
                sprintf('Connection "%s" is not registered.', $name),
            );
        }

        return $this->connections[$name];
    }

    public function setDefault(string $name): void
    {
        if (! isset($this->connections[$name])) {
            throw new InvalidArgumentException(
                sprintf('Cannot set default: connection "%s" is not registered.', $name),
            );
        }

        $this->defaultName = $name;
    }

    public function defaultName(): string
    {
        return $this->defaultName;
    }

    /**
     * @return array<string, ConnectionInterface>
     */
    public function all(): array
    {
        return $this->connections;
    }
}
