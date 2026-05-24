<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Strategy;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Strategy\CreateStrategy;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Persistence\RepositoryInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Domain\Service\FillableGuard;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakeResourceDefinition;
use PHPUnit\Framework\TestCase;

final class CreateStrategyTest extends TestCase
{
    public function test_returns_success_with_inserted_id(): void
    {
        $repo = new class implements RepositoryInterface {
            public function find(ResourceId $id): ?array
            {
                unset($id);
                return null;
            }
            public function insert(array $data): ResourceId
            {
                return new ResourceId(99);
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
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array
            {
                return [];
            }
            public function updateBulk(array $criteria, array $data): int
            {
                return 0;
            }
            public function deleteBulk(array $criteria): int
            {
                return 0;
            }
        };

        $strategy = $this->makeStrategy($repo);
        $result = $strategy->execute($this->context(['name' => 'Ada', 'email' => 'a@b.com']));

        self::assertTrue($result->success);
        self::assertSame(99, $result->data['id']);
        self::assertSame('Ada', $result->data['name']);
    }

    public function test_guarded_fields_are_stripped(): void
    {
        $captured = [];
        $repo = new class ($captured) implements RepositoryInterface {
            public function __construct(private array &$captured)
            {
            }

            public function find(ResourceId $id): ?array
            {
                unset($id);
                return null;
            }
            public function insert(array $data): ResourceId
            {
                $this->captured = $data;

                return new ResourceId(1);
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
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array
            {
                return [];
            }
            public function updateBulk(array $criteria, array $data): int
            {
                return 0;
            }
            public function deleteBulk(array $criteria): int
            {
                return 0;
            }
        };

        $strategy = $this->makeStrategy($repo, guarded: ['id']);
        $strategy->execute($this->context(['id' => 5, 'name' => 'Ada']));

        self::assertArrayNotHasKey('id', $captured);
        self::assertArrayHasKey('name', $captured);
    }

    public function test_meta_contains_operation(): void
    {
        $repo = new class implements RepositoryInterface {
            public function find(ResourceId $id): ?array
            {
                unset($id);
                return null;
            }
            public function insert(array $data): ResourceId
            {
                unset($data);
                return new ResourceId(1);
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
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array
            {
                return [];
            }
            public function updateBulk(array $criteria, array $data): int
            {
                return 0;
            }
            public function deleteBulk(array $criteria): int
            {
                return 0;
            }
        };

        $result = $this->makeStrategy($repo)->execute($this->context(['name' => 'Ada']));

        self::assertSame('create', $result->meta['operation']);
    }

    /**
     * @param list<string> $guarded
     */
    private function makeStrategy(
        RepositoryInterface $repo,
        array $guarded = ['id'],
    ): CreateStrategy {
        $def = new FakeResourceDefinition(guarded: $guarded);
        $resources = new ResourceRegistry(['users' => $def]);
        $resolver = new RepositoryResolver(['users' => $repo]);

        return new CreateStrategy($resolver, $resources, new FillableGuard());
    }

    /**
     * @param array<string, mixed> $input
     */
    private function context(array $input = []): CrudContext
    {
        return new CrudContext(
            OperationType::Create,
            'users',
            $input,
            null,
            new FakeCrudRequest('POST', '/users', $input),
        );
    }
}
