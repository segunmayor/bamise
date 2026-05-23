<?php

declare(strict_types=1);

namespace Bamise\Contract\Persistence;

use Bamise\Contract\ValueObject\ResourceId;

interface RepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(ResourceId $id): ?array;

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): ResourceId;

    /**
     * @param array<string, mixed> $data
     */
    public function update(ResourceId $id, array $data): bool;

    public function delete(ResourceId $id): bool;

    /**
     * @param array<string, mixed> $criteria
     * @return list<array<string, mixed>>
     */
    public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array;

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, mixed> $data
     */
    public function updateBulk(array $criteria, array $data): int;

    /**
     * @param array<string, mixed> $criteria
     */
    public function deleteBulk(array $criteria): int;
}
