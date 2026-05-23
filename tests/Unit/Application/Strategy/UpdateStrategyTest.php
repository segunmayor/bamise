<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Strategy;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Strategy\UpdateStrategy;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Persistence\RepositoryInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Domain\Service\FillableGuard;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakeResourceDefinition;
use PHPUnit\Framework\TestCase;

final class UpdateStrategyTest extends TestCase
{
    public function test_returns_success_with_merged_data(): void
    {
        $strategy = $this->makeStrategy(updated: true);
        $result = $strategy->execute($this->context(['id' => 5, 'name' => 'Bob']));

        self::assertTrue($result->success);
        self::assertSame(5, $result->data['id']);
        self::assertSame('Bob', $result->data['name']);
    }

    public function test_returns_failure_when_no_id_in_input(): void
    {
        $result = $this->makeStrategy()->execute($this->context(['name' => 'Bob']));

        self::assertFalse($result->success);
        self::assertArrayHasKey('message', $result->errors);
    }

    public function test_returns_failure_when_repository_update_returns_false(): void
    {
        $result = $this->makeStrategy(updated: false)->execute($this->context(['id' => 9, 'name' => 'Bob']));

        self::assertFalse($result->success);
    }

    public function test_primary_key_is_stripped_from_update_payload(): void
    {
        $captured = [];
        $repo = new class ($captured) implements RepositoryInterface {
            public function __construct(private array &$captured)
            {
            }

            public function find(ResourceId $id): ?array { unset($id); return null; }
            public function insert(array $data): ResourceId { unset($data); return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool
            {
                $this->captured = $data;

                return true;
            }

            public function delete(ResourceId $id): bool { unset($id); return true; }
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array { return []; }
            public function updateBulk(array $criteria, array $data): int { return 0; }
            public function deleteBulk(array $criteria): int { return 0; }
        };

        $def = new FakeResourceDefinition();
        $strategy = new UpdateStrategy(
            new RepositoryResolver(['users' => $repo]),
            new ResourceRegistry(['users' => $def]),
            new FillableGuard(),
        );
        $strategy->execute($this->context(['id' => 1, 'name' => 'Ada']));

        self::assertArrayNotHasKey('id', $captured);
    }

    private function makeStrategy(bool $updated = true): UpdateStrategy
    {
        $repo = new class ($updated) implements RepositoryInterface {
            public function __construct(private bool $updated)
            {
            }

            public function find(ResourceId $id): ?array { unset($id); return null; }
            public function insert(array $data): ResourceId { unset($data); return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool { unset($id, $data); return $this->updated; }
            public function delete(ResourceId $id): bool { unset($id); return true; }
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array { return []; }
            public function updateBulk(array $criteria, array $data): int { return 0; }
            public function deleteBulk(array $criteria): int { return 0; }
        };

        $def = new FakeResourceDefinition();

        return new UpdateStrategy(
            new RepositoryResolver(['users' => $repo]),
            new ResourceRegistry(['users' => $def]),
            new FillableGuard(),
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    private function context(array $input): CrudContext
    {
        return new CrudContext(
            OperationType::Update,
            'users',
            $input,
            null,
            new FakeCrudRequest('PATCH', '/users', $input),
        );
    }
}
