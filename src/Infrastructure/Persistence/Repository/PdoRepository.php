<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Persistence\Repository;

use Bamise\Contract\Persistence\ConnectionInterface;
use Bamise\Contract\Persistence\RepositoryInterface;
use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Infrastructure\Persistence\Query\CompiledQuery;
use Bamise\Infrastructure\Persistence\Query\SqlCompiler;
use PDO;

final class PdoRepository implements RepositoryInterface
{
    /**
     * @param list<string> $fillable
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly SqlCompiler $compiler,
        private readonly string $table,
        private readonly string $primaryKey,
        private readonly array $fillable = [],
    ) {
    }

    public function find(ResourceId $id): ?array
    {
        $query = $this->compiler->compileSelectById($this->table, $this->primaryKey, $id->value);

        return $this->fetchOne($query);
    }

    public function insert(array $data): ResourceId
    {
        $data = $this->compiler->whitelistColumns($this->fillable, $data);
        $query = $this->compiler->compileInsert($this->table, $this->primaryKey, $data);

        if ($this->connection->dialect()->supportsReturning()) {
            $row = $this->fetchOne($query);

            if ($row === null || ! array_key_exists($this->primaryKey, $row)) {
                throw new \RuntimeException('Insert did not return a primary key.');
            }

            return new ResourceId($row[$this->primaryKey]);
        }

        $this->execute($query);
        $lastId = $this->connection->pdo()->lastInsertId();

        if ($lastId === false || $lastId === '0') {
            if (array_key_exists($this->primaryKey, $data)) {
                return new ResourceId($data[$this->primaryKey]);
            }

            throw new \RuntimeException('Unable to resolve inserted primary key.');
        }

        return new ResourceId(is_numeric($lastId) ? (int) $lastId : $lastId);
    }

    public function update(ResourceId $id, array $data): bool
    {
        $data = $this->compiler->whitelistColumns($this->fillable, $data);
        $query = $this->compiler->compileUpdate($this->table, $this->primaryKey, $id->value, $data);

        return $this->execute($query) > 0;
    }

    public function delete(ResourceId $id): bool
    {
        $query = $this->compiler->compileDelete($this->table, $this->primaryKey, $id->value);

        return $this->execute($query) > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchOne(CompiledQuery $query): ?array
    {
        $statement = $this->connection->pdo()->prepare($query->sql);
        $statement->execute($query->bindings);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $row;
    }

    private function execute(CompiledQuery $query): int
    {
        $statement = $this->connection->pdo()->prepare($query->sql);
        $statement->execute($query->bindings);

        return $statement->rowCount();
    }
}
