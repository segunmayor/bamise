<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Strategy;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Strategy\BulkDeleteStrategy;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Persistence\RepositoryInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class BulkDeleteStrategyTest extends TestCase
{
    public function test_returns_affected_count_on_success(): void
    {
        $result = $this->makeStrategy(affected: 3)->execute($this->context([]));

        self::assertTrue($result->success);
        self::assertSame(3, $result->data['affected']);
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
            public function updateBulk(array $criteria, array $data): int { return 0; }
            public function deleteBulk(array $criteria): int
            {
                $this->captured = $criteria;

                return 0;
            }
        };

        $strategy = new BulkDeleteStrategy(new RepositoryResolver(['users' => $repo]));
        $strategy->execute($this->context(['_criteria' => ['status' => 'archived']]));

        self::assertSame(['status' => 'archived'], $capturedCriteria);
    }

    public function test_no_criteria_deletes_all_records(): void
    {
        $capturedCriteria = ['not-empty'];
        $repo = new class ($capturedCriteria) implements RepositoryInterface {
            public function __construct(private mixed &$captured)
            {
            }

            public function find(ResourceId $id): ?array { unset($id); return null; }
            public function insert(array $data): ResourceId { unset($data); return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool { unset($id, $data); return true; }
            public function delete(ResourceId $id): bool { unset($id); return true; }
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array { return []; }
            public function updateBulk(array $criteria, array $data): int { return 0; }
            public function deleteBulk(array $criteria): int
            {
                $this->captured = $criteria;

                return 10;
            }
        };

        $strategy = new BulkDeleteStrategy(new RepositoryResolver(['users' => $repo]));
        $strategy->execute($this->context([]));

        self::assertSame([], $capturedCriteria);
    }

    public function test_meta_contains_bulk_delete_operation(): void
    {
        $result = $this->makeStrategy()->execute($this->context([]));

        self::assertSame('bulk_delete', $result->meta['operation']);
    }

    private function makeStrategy(int $affected = 0): BulkDeleteStrategy
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
            public function updateBulk(array $criteria, array $data): int { return 0; }
            public function deleteBulk(array $criteria): int { unset($criteria); return $this->affected; }
        };

        return new BulkDeleteStrategy(new RepositoryResolver(['users' => $repo]));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function context(array $input): CrudContext
    {
        return new CrudContext(
            OperationType::BulkDelete,
            'users',
            $input,
            null,
            new FakeCrudRequest('DELETE', '/users', $input),
        );
    }
}
