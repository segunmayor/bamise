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
}
