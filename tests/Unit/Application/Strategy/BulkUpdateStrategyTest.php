<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Strategy;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Strategy\BulkUpdateStrategy;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Persistence\RepositoryInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Domain\Service\FillableGuard;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakeResourceDefinition;
use PHPUnit\Framework\TestCase;

final class BulkUpdateStrategyTest extends TestCase
{
    public function test_returns_affected_count_on_success(): void
    {
        $result = $this->makeStrategy(affected: 5)->execute($this->context(['name' => 'Ada']));

        self::assertTrue($result->success);
        self::assertSame(5, $result->data['affected']);
    }

    public function test_criteria_from_input_are_forwarded_to_repository(): void
    {
        $capturedCriteria = null;
        $repo = new class ($capturedCriteria) implements RepositoryInterface {
            public function __construct(private mixed &$captured)
            {
            }

            public function find(ResourceId $id): ?array { unset($id); return null; }
            public function insert(array $data): ResourceId { unset($data); return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool { unset($id, $data); return true; }
            public function delete(ResourceId $id): bool { unset($id); return true; }
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array { return []; }
            public function updateBulk(array $criteria, array $data): int
            {
                $this->captured = $criteria;

                return 0;
            }

            public function deleteBulk(array $criteria): int { return 0; }
        };

        $strategy = new BulkUpdateStrategy(
            new RepositoryResolver(['users' => $repo]),
            new ResourceRegistry(['users' => new FakeResourceDefinition()]),
            new FillableGuard(),
        );
        $strategy->execute($this->context(['_criteria' => ['status' => 'pending'], 'name' => 'Ada']));

        self::assertSame(['status' => 'pending'], $capturedCriteria);
    }

    public function test_guarded_fields_are_stripped_from_payload(): void
    {
        $capturedData = null;
        $repo = new class ($capturedData) implements RepositoryInterface {
            public function __construct(private mixed &$captured)
            {
            }

            public function find(ResourceId $id): ?array { unset($id); return null; }
            public function insert(array $data): ResourceId { unset($data); return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool { unset($id, $data); return true; }
            public function delete(ResourceId $id): bool { unset($id); return true; }
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array { return []; }
            public function updateBulk(array $criteria, array $data): int
            {
                $this->captured = $data;

                return 0;
            }

            public function deleteBulk(array $criteria): int { return 0; }
        };

        $strategy = new BulkUpdateStrategy(
            new RepositoryResolver(['users' => $repo]),
            new ResourceRegistry(['users' => new FakeResourceDefinition(guarded: ['id'])]),
            new FillableGuard(),
        );
        $strategy->execute($this->context(['id' => 9, 'name' => 'Ada']));

        self::assertArrayNotHasKey('id', $capturedData);
        self::assertArrayHasKey('name', $capturedData);
    }

    public function test_returns_failure_when_no_update_data_provided(): void
    {
        $result = $this->makeStrategy()->execute($this->context(['_criteria' => ['status' => 'old']]));

        self::assertFalse($result->success);
        self::assertArrayHasKey('message', $result->errors);
    }

    public function test_meta_contains_bulk_update_operation(): void
    {
        $result = $this->makeStrategy()->execute($this->context(['name' => 'Ada']));

        self::assertSame('bulk_update', $result->meta['operation']);
    }

    private function makeStrategy(int $affected = 0): BulkUpdateStrategy
    {
        $repo = new class ($affected) implements RepositoryInterface {
            public function __construct(private int $affected)
            {
            }

            public function find(ResourceId $id): ?array { unset($id); return null; }
            public function insert(array $data): ResourceId { unset($data); return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool { unset($id, $data); return true; }
            public function delete(ResourceId $id): bool { unset($id); return true; }
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array { return []; }
            public function updateBulk(array $criteria, array $data): int { unset($criteria, $data); return $this->affected; }
            public function deleteBulk(array $criteria): int { return 0; }
        };

        return new BulkUpdateStrategy(
            new RepositoryResolver(['users' => $repo]),
            new ResourceRegistry(['users' => new FakeResourceDefinition()]),
            new FillableGuard(),
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    private function context(array $input): CrudContext
    {
        return new CrudContext(
            OperationType::BulkUpdate,
            'users',
            $input,
            null,
            new FakeCrudRequest('PATCH', '/users', $input),
        );
    }
}
