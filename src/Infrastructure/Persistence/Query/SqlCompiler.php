<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Persistence\Query;

use Bamise\Contract\Persistence\DatabaseDialectInterface;

final class SqlCompiler
{
    public function __construct(
        private readonly DatabaseDialectInterface $dialect,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function compileInsert(string $table, string $primaryKey, array $data): CompiledQuery
    {
        $columns = array_keys($data);

        if ($columns === []) {
            throw new \InvalidArgumentException('Insert requires at least one column.');
        }

        $quotedTable = $this->dialect->quoteIdentifier($table);
        $quotedColumns = array_map(
            fn (string $column): string => $this->dialect->quoteIdentifier($column),
            $columns,
        );
        $placeholders = array_map(
            static fn (string $column): string => ':' . $column,
            $columns,
        );

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $quotedTable,
            implode(', ', $quotedColumns),
            implode(', ', $placeholders),
        );

        if ($this->dialect->supportsReturning()) {
            $sql .= sprintf(
                ' RETURNING %s',
                $this->dialect->quoteIdentifier($primaryKey),
            );
        }

        return new CompiledQuery($sql, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function compileUpdate(
        string $table,
        string $primaryKey,
        string|int $id,
        array $data,
    ): CompiledQuery {
        if ($data === []) {
            throw new \InvalidArgumentException('Update requires at least one column.');
        }

        $setParts = [];
        $bindings = ['__pk' => $id];

        foreach ($data as $column => $value) {
            if ($column === $primaryKey) {
                continue;
            }

            $placeholder = ':' . $column;
            $setParts[] = sprintf(
                '%s = %s',
                $this->dialect->quoteIdentifier($column),
                $placeholder,
            );
            $bindings[$column] = $value;
        }

        if ($setParts === []) {
            throw new \InvalidArgumentException('Update requires at least one mutable column.');
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :__pk',
            $this->dialect->quoteIdentifier($table),
            implode(', ', $setParts),
            $this->dialect->quoteIdentifier($primaryKey),
        );

        return new CompiledQuery($sql, $bindings);
    }

    public function compileDelete(string $table, string $primaryKey, string|int $id): CompiledQuery
    {
        $sql = sprintf(
            'DELETE FROM %s WHERE %s = :__pk',
            $this->dialect->quoteIdentifier($table),
            $this->dialect->quoteIdentifier($primaryKey),
        );

        return new CompiledQuery($sql, ['__pk' => $id]);
    }

    public function compileSelectById(string $table, string $primaryKey, string|int $id): CompiledQuery
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = :__pk LIMIT 1',
            $this->dialect->quoteIdentifier($table),
            $this->dialect->quoteIdentifier($primaryKey),
        );

        return new CompiledQuery($sql, ['__pk' => $id]);
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function compileSelectAll(
        string $table,
        array $criteria = [],
        int $limit = 100,
        int $offset = 0,
    ): CompiledQuery {
        $quotedTable = $this->dialect->quoteIdentifier($table);
        $bindings = [];
        $sql = sprintf('SELECT * FROM %s', $quotedTable);

        if ($criteria !== []) {
            $whereParts = [];

            foreach ($criteria as $column => $value) {
                $whereParts[] = sprintf(
                    '%s = :__w_%s',
                    $this->dialect->quoteIdentifier($column),
                    $column,
                );
                $bindings['__w_' . $column] = $value;
            }

            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        $sql .= sprintf(' LIMIT %d OFFSET %d', $limit, $offset);

        return new CompiledQuery($sql, $bindings);
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, mixed> $data
     */
    public function compileUpdateWhere(string $table, array $criteria, array $data): CompiledQuery
    {
        if ($data === []) {
            throw new \InvalidArgumentException('Bulk update requires at least one column.');
        }

        $quotedTable = $this->dialect->quoteIdentifier($table);
        $setParts = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $setParts[] = sprintf(
                '%s = :%s',
                $this->dialect->quoteIdentifier($column),
                $column,
            );
            $bindings[$column] = $value;
        }

        $sql = sprintf('UPDATE %s SET %s', $quotedTable, implode(', ', $setParts));

        if ($criteria !== []) {
            $whereParts = [];

            foreach ($criteria as $column => $value) {
                $whereParts[] = sprintf(
                    '%s = :__w_%s',
                    $this->dialect->quoteIdentifier($column),
                    $column,
                );
                $bindings['__w_' . $column] = $value;
            }

            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        return new CompiledQuery($sql, $bindings);
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function compileDeleteWhere(string $table, array $criteria): CompiledQuery
    {
        $quotedTable = $this->dialect->quoteIdentifier($table);
        $bindings = [];
        $sql = sprintf('DELETE FROM %s', $quotedTable);

        if ($criteria !== []) {
            $whereParts = [];

            foreach ($criteria as $column => $value) {
                $whereParts[] = sprintf(
                    '%s = :__w_%s',
                    $this->dialect->quoteIdentifier($column),
                    $column,
                );
                $bindings['__w_' . $column] = $value;
            }

            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        return new CompiledQuery($sql, $bindings);
    }

    /**
     * @param list<string> $allowedColumns
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function whitelistColumns(array $allowedColumns, array $data): array
    {
        if ($allowedColumns === []) {
            return $data;
        }

        $allowed = array_flip($allowedColumns);

        return array_intersect_key($data, $allowed);
    }
}
