<?php

declare(strict_types=1);

namespace Bamise\Tests\Fixtures;

use Bamise\Contract\Persistence\RepositoryInterface;
use Bamise\Contract\ValueObject\ResourceId;

final class FakeRepository implements RepositoryInterface
{
    public function find(ResourceId $id): ?array
    {
        unset($id);

        return null;
    }

    public function insert(array $data): ResourceId
    {
        return new ResourceId($data['id'] ?? 1);
    }

    public function update(ResourceId $id, array $data): bool
    {
        unset($id, $data);

        return true;
    }

    public function delete(ResourceId $id): bool
    {
        unset($id);

        return true;
    }
}
