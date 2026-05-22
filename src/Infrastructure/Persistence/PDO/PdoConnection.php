<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Persistence\PDO;

use Bamise\Contract\Persistence\ConnectionInterface;
use Bamise\Contract\Persistence\DatabaseDialectInterface;
use PDO;
use Throwable;

final class PdoConnection implements ConnectionInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly DatabaseDialectInterface $dialect,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public static function fromConfig(ConnectionConfig $config): self
    {
        $pdo = new PDO($config->dsn, $config->user, $config->password);
        $dialect = Dialect\DialectFactory::fromDriver($config->driver);

        return new self($pdo, $dialect);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function dialect(): DatabaseDialectInterface
    {
        return $this->dialect;
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $callback();
        }

        $this->pdo->beginTransaction();

        try {
            $result = $callback();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $throwable) {
            try {
                $this->pdo->rollBack();
            } catch (Throwable) {
                // Transaction was already ended (e.g. by a failed commit)
            }

            throw $throwable;
        }
    }
}
