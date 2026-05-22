<?php

declare(strict_types=1);

namespace Bamise\Contract\Persistence;

interface QueryBuilderInterface
{
    public function table(string $table): self;

    /**
     * @param list<string> $columns
     */
    public function select(array $columns = ['*']): self;

    public function where(string $column, mixed $operator, mixed $value = null): self;

    public function orderBy(string $column, string $direction = 'asc'): self;

    public function limit(int $limit): self;

    public function offset(int $offset): self;

    /**
     * @return list<array<string, mixed>>
     */
    public function get(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array;
}
