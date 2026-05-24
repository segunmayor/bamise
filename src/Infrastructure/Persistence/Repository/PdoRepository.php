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

    #[\Override]
    public function find(ResourceId $id): ?array
    {
        $query = $this->compiler->compileSelectById($this->table, $this->primaryKey, $id->value);

        return $this->fetchOne($query);
    }

    #[\Override]
    public function insert(array $data): ResourceId
    {
        $data = $this->compiler->whitelistColumns($this->fillable, $data);
        $query = $this->compiler->compileInsert($this->table, $this->primaryKey, $data);

        if ($this->connection->dialect()->supportsReturning()) {
            $row = $this->fetchOne($query);

            if ($row === null || ! array_key_exists($this->primaryKey, $row)) {
                throw new \RuntimeException('Insert did not return a primary key.');
            }

            $pkValue = $row[$this->primaryKey];

            if (! is_int($pkValue) && ! is_string($pkValue)) {
                throw new \RuntimeException('Insert did not return a usable primary key value.');
            }

            return new ResourceId($pkValue);
        }

        $this->execute($query);
        $lastId = $this->connection->pdo()->lastInsertId();

        if ($lastId === false || $lastId === '0') {
            if (array_key_exists($this->primaryKey, $data)) {
                $pkFallback = $data[$this->primaryKey];

                if (! is_int($pkFallback) && ! is_string($pkFallback)) {
                    throw new \RuntimeException('Unable to resolve inserted primary key.');
                }

                return new ResourceId($pkFallback);
            }

            throw new \RuntimeException('Unable to resolve inserted primary key.');
        }

        return new ResourceId(is_numeric($lastId) ? (int) $lastId : $lastId);
    }

    #[\Override]
    public function update(ResourceId $id, array $data): bool
    {
        $data = $this->compiler->whitelistColumns($this->fillable, $data);
        $query = $this->compiler->compileUpdate($this->table, $this->primaryKey, $id->value, $data);

        return $this->execute($query) > 0;
    }

    #[\Override]
    public function delete(ResourceId $id): bool
    {
        $query = $this->compiler->compileDelete($this->table, $this->primaryKey, $id->value);

        return $this->execute($query) > 0;
    }

    #[\Override]
    public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array
    {
        $query = $this->compiler->compileSelectAll($this->table, $criteria, $limit, $offset);

        return $this->fetchAll($query);
    }

    #[\Override]
    public function updateBulk(array $criteria, array $data): int
    {
        $data = $this->compiler->whitelistColumns($this->fillable, $data);
        $query = $this->compiler->compileUpdateWhere($this->table, $criteria, $data);

        return $this->execute($query);
    }

    #[\Override]
    public function deleteBulk(array $criteria): int
    {
        $query = $this->compiler->compileDeleteWhere($this->table, $criteria);

        return $this->execute($query);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchOne(CompiledQuery $query): ?array
    {
        $statement = $this->connection->pdo()->prepare($query->sql);
        $statement->execute($query->bindings);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (! is_array($row)) {
            return null;
        }

        $result = [];

        foreach ($row as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function execute(CompiledQuery $query): int
    {
        $statement = $this->connection->pdo()->prepare($query->sql);
        $statement->execute($query->bindings);

        return $statement->rowCount();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAll(CompiledQuery $query): array
    {
        $statement = $this->connection->pdo()->prepare($query->sql);
        $statement->execute($query->bindings);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? array_values($rows) : [];
    }
}
