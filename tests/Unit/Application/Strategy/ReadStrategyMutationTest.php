<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Strategy;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Strategy\ReadStrategy;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Persistence\RepositoryInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakeRepository;
use Bamise\Tests\Fixtures\FakeResourceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for ReadStrategy.
 *
 * Kills escaped mutants:
 * - Line 28: Coalesce ($primaryKey ?? $inputData['id'] ?? null)
 * - Lines 42,46: ArrayItem/ArrayItemRemoval in single-record meta
 * - Lines 49-53: CastInt/DecrementInteger/IncrementInteger on limit/offset
 * - Lines 60,69: ArrayItem/ArrayItemRemoval in list meta (items/count)
 * - Line 89: ArrayOneItem (single-item list result)
 */
final class ReadStrategyMutationTest extends TestCase
{
    private ReadStrategy $strategy;
    private ResourceRegistry $resources;
    private RepositoryResolver $repos;

    protected function setUp(): void
    {
        $this->resources = new ResourceRegistry();
        $this->repos = new RepositoryResolver();
    }

    private function makeStrategy(): ReadStrategy
    {
        return new ReadStrategy($this->repos, $this->resources);
    }

    private function context(array $inputData = []): CrudContext
    {
        return new CrudContext(
            OperationType::Read,
            'products',
            $inputData,
            null,
            new FakeCrudRequest(),
        );
    }

    // ── Line 28: Coalesce — primaryKey field vs 'id' fallback ────────────────

    public function test_find_by_primary_key_field_name(): void
    {
        $definition = new FakeResourceDefinition(primaryKey: 'product_id');
        $this->resources->register('products', $definition);

        $repo = $this->createConfiguredRepo(fn ($id) => ['product_id' => $id->value, 'name' => 'Widget']);
        $this->repos->register('products', $repo);

        $result = $this->makeStrategy()->execute($this->context(['product_id' => 5]));

        self::assertTrue($result->success);
        self::assertSame(['product_id' => 5, 'name' => 'Widget'], $result->data);
    }

    public function test_find_falls_back_to_id_field(): void
    {
        $definition = new FakeResourceDefinition(primaryKey: 'product_id');
        $this->resources->register('products', $definition);

        $repo = $this->createConfiguredRepo(fn ($id) => ['id' => $id->value]);
        $this->repos->register('products', $repo);

        // 'product_id' not in input, but 'id' is
        $result = $this->makeStrategy()->execute($this->context(['id' => 7]));

        self::assertTrue($result->success);
    }

    // ── Lines 42,46: ArrayItem in single-record result meta ───────────────────

    public function test_single_record_result_has_operation_in_meta(): void
    {
        $this->resources->register('products', new FakeResourceDefinition());
        $repo = $this->createConfiguredRepo(fn ($id) => ['id' => $id->value, 'name' => 'Test']);
        $this->repos->register('products', $repo);

        $result = $this->makeStrategy()->execute($this->context(['id' => 1]));

        self::assertArrayHasKey('operation', $result->meta);
        self::assertSame('read', $result->meta['operation']);
    }

    // ── Lines 49-53: limit/offset parsing ────────────────────────────────────

    public function test_default_limit_is_100(): void
    {
        $this->resources->register('products', new FakeResourceDefinition());
        $capturedLimit = null;
        $repo = $this->createCapturingRepo($capturedLimit);
        $this->repos->register('products', $repo);

        $this->makeStrategy()->execute($this->context([]));

        self::assertSame(100, $capturedLimit);
    }

    public function test_default_offset_is_0(): void
    {
        $this->resources->register('products', new FakeResourceDefinition());
        $capturedOffset = null;
        $repo = $this->createCapturingRepoOffset($capturedOffset);
        $this->repos->register('products', $repo);

        $this->makeStrategy()->execute($this->context([]));

        self::assertSame(0, $capturedOffset);
    }

    public function test_explicit_limit_overrides_default(): void
    {
        $this->resources->register('products', new FakeResourceDefinition());
        $capturedLimit = null;
        $repo = $this->createCapturingRepo($capturedLimit);
        $this->repos->register('products', $repo);

        $this->makeStrategy()->execute($this->context(['limit' => '25']));

        self::assertSame(25, $capturedLimit);
    }

    public function test_limit_below_one_is_clamped_to_one(): void
    {
        // max(1, (int) $limit) — DecrementInteger would change max(1,...) to max(0,...)
        $this->resources->register('products', new FakeResourceDefinition());
        $capturedLimit = null;
        $repo = $this->createCapturingRepo($capturedLimit);
        $this->repos->register('products', $repo);

        $this->makeStrategy()->execute($this->context(['limit' => '0']));

        self::assertGreaterThanOrEqual(1, $capturedLimit);
    }

    public function test_negative_limit_is_clamped_to_one(): void
    {
        $this->resources->register('products', new FakeResourceDefinition());
        $capturedLimit = null;
        $repo = $this->createCapturingRepo($capturedLimit);
        $this->repos->register('products', $repo);

        $this->makeStrategy()->execute($this->context(['limit' => '-10']));

        self::assertSame(1, $capturedLimit);
    }

    public function test_explicit_offset_overrides_default(): void
    {
        $this->resources->register('products', new FakeResourceDefinition());
        $capturedOffset = null;
        $repo = $this->createCapturingRepoOffset($capturedOffset);
        $this->repos->register('products', $repo);

        $this->makeStrategy()->execute($this->context(['offset' => '50']));

        self::assertSame(50, $capturedOffset);
    }

    public function test_negative_offset_is_clamped_to_zero(): void
    {
        // max(0, (int) $offset) — DecrementInteger would change max(0,...) to max(-1,...)
        $this->resources->register('products', new FakeResourceDefinition());
        $capturedOffset = null;
        $repo = $this->createCapturingRepoOffset($capturedOffset);
        $this->repos->register('products', $repo);

        $this->makeStrategy()->execute($this->context(['offset' => '-5']));

        self::assertSame(0, $capturedOffset);
    }

    public function test_non_numeric_limit_defaults_to_100(): void
    {
        $this->resources->register('products', new FakeResourceDefinition());
        $capturedLimit = null;
        $repo = $this->createCapturingRepo($capturedLimit);
        $this->repos->register('products', $repo);

        $this->makeStrategy()->execute($this->context(['limit' => 'bad']));

        self::assertSame(100, $capturedLimit);
    }

    // ── Lines 60,69: ArrayItem in list result meta ───────────────────────────

    public function test_list_result_has_items_key_in_data(): void
    {
        $this->resources->register('products', new FakeResourceDefinition());
        $this->repos->register('products', new FakeRepository());

        $result = $this->makeStrategy()->execute($this->context([]));

        self::assertArrayHasKey('items', $result->data);
    }

    public function test_list_result_meta_has_operation_key(): void
    {
        $this->resources->register('products', new FakeResourceDefinition());
        $this->repos->register('products', new FakeRepository());

        $result = $this->makeStrategy()->execute($this->context([]));

        self::assertArrayHasKey('operation', $result->meta);
        self::assertSame('read', $result->meta['operation']);
    }

    public function test_list_result_meta_has_count_key(): void
    {
        $this->resources->register('products', new FakeResourceDefinition());
        $this->repos->register('products', new FakeRepository());

        $result = $this->makeStrategy()->execute($this->context([]));

        self::assertArrayHasKey('count', $result->meta);
        self::assertSame(0, $result->meta['count']);
    }

    // ── Line 89: ArrayOneItem — single list result ───────────────────────────

    public function test_list_result_with_one_item_has_count_one(): void
    {
        $this->resources->register('products', new FakeResourceDefinition());

        $repo = new class implements RepositoryInterface {
            public function find(ResourceId $id): ?array { return null; }
            public function insert(array $data): ResourceId { return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool { return true; }
            public function delete(ResourceId $id): bool { return true; }
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array
            {
                return [['id' => 1, 'name' => 'Solo']];
            }
            public function updateBulk(array $criteria, array $data): int { return 0; }
            public function deleteBulk(array $criteria): int { return 0; }
        };

        $this->repos->register('products', $repo);

        $result = $this->makeStrategy()->execute($this->context([]));

        self::assertSame(1, $result->meta['count']);
        self::assertCount(1, $result->data['items']);
    }

    // ── Not-found path ───────────────────────────────────────────────────────

    public function test_find_returns_not_found_when_row_is_null(): void
    {
        $this->resources->register('products', new FakeResourceDefinition());
        // FakeRepository::find() always returns null
        $this->repos->register('products', new FakeRepository());

        $result = $this->makeStrategy()->execute($this->context(['id' => 999]));

        self::assertFalse($result->success);
        self::assertArrayHasKey('message', $result->errors);
    }

    // ── Line 69: ArrayItem/ArrayItemRemoval — notFound() meta has 'operation' ─

    public function test_not_found_result_has_operation_in_meta(): void
    {
        // FakeResourceDefinition has primaryKey='id', FakeRepository::find returns null
        $this->resources->register('products', new FakeResourceDefinition());
        $this->repos->register('products', new FakeRepository());

        $result = $this->makeStrategy()->execute($this->context(['id' => 42]));

        // ArrayItem mutation: meta: ['operation' > 'read'] = [false]
        // ArrayItemRemoval mutation: meta: []
        self::assertArrayHasKey('operation', $result->meta, 'notFound() must have operation in meta');
        self::assertSame('read', $result->meta['operation']);
    }

    // ── Line 28: Coalesce — when both primaryKey AND 'id' present, use primaryKey ─

    public function test_coalesce_uses_primary_key_over_id_when_both_present(): void
    {
        $definition = new FakeResourceDefinition(primaryKey: 'product_id');
        $this->resources->register('products', $definition);

        $capturedId = null;
        $repo = new class ($capturedId) implements RepositoryInterface {
            public function __construct(private ?int &$captured) {}
            public function find(ResourceId $id): ?array
            {
                $this->captured = (int) $id->value;
                return ['product_id' => $id->value, 'name' => 'Found'];
            }
            public function insert(array $data): ResourceId { return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool { return true; }
            public function delete(ResourceId $id): bool { return true; }
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array { return []; }
            public function updateBulk(array $criteria, array $data): int { return 0; }
            public function deleteBulk(array $criteria): int { return 0; }
        };
        $this->repos->register('products', $repo);

        // Both product_id=5 and id=99 present with DIFFERENT values
        // Original: uses product_id=5
        // Coalesce mutant: uses id=99
        $result = $this->makeStrategy()->execute($this->context(['product_id' => 5, 'id' => 99]));

        self::assertTrue($result->success);
        self::assertSame(5, $capturedId, 'Should use primary key field (product_id=5), not id fallback (id=99)');
    }

    // ── Line 46: ArrayItemRemoval — primaryKey must be in reserved ────────────

    public function test_primary_key_field_excluded_from_criteria(): void
    {
        $definition = new FakeResourceDefinition(primaryKey: 'product_id');
        $this->resources->register('products', $definition);

        $capturedCriteria = null;
        $repo = new class ($capturedCriteria) implements RepositoryInterface {
            public function __construct(private ?array &$captured) {}
            public function find(ResourceId $id): ?array { return null; }
            public function insert(array $data): ResourceId { return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool { return true; }
            public function delete(ResourceId $id): bool { return true; }
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array
            {
                $this->captured = $criteria;
                return [];
            }
            public function updateBulk(array $criteria, array $data): int { return 0; }
            public function deleteBulk(array $criteria): int { return 0; }
        };
        $this->repos->register('products', $repo);

        // inputData has 'product_id' (null → no single-record path) and 'category'
        // product_id should be in reserved → NOT appear in criteria
        $this->makeStrategy()->execute($this->context(['product_id' => null, 'category' => 'electronics']));

        self::assertNotNull($capturedCriteria);
        self::assertArrayNotHasKey('product_id', $capturedCriteria, 'Primary key field must be excluded from criteria');
        self::assertArrayHasKey('category', $capturedCriteria);
    }

    // ── Line 89: ArrayOneItem — extractCriteria returns ALL criteria items ────

    public function test_extract_criteria_returns_all_matching_fields(): void
    {
        $definition = new FakeResourceDefinition(primaryKey: 'id');
        $this->resources->register('products', $definition);

        $capturedCriteria = null;
        $repo = new class ($capturedCriteria) implements RepositoryInterface {
            public function __construct(private ?array &$captured) {}
            public function find(ResourceId $id): ?array { return null; }
            public function insert(array $data): ResourceId { return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool { return true; }
            public function delete(ResourceId $id): bool { return true; }
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array
            {
                $this->captured = $criteria;
                return [];
            }
            public function updateBulk(array $criteria, array $data): int { return 0; }
            public function deleteBulk(array $criteria): int { return 0; }
        };
        $this->repos->register('products', $repo);

        // Pass 2 non-reserved criteria fields. ArrayOneItem mutant slices to 1 when count > 1.
        $this->makeStrategy()->execute($this->context(['category' => 'tools', 'brand' => 'Acme']));

        self::assertNotNull($capturedCriteria);
        self::assertArrayHasKey('category', $capturedCriteria, 'extractCriteria must return all criteria items');
        self::assertArrayHasKey('brand', $capturedCriteria, 'extractCriteria must return all criteria items');
        self::assertCount(2, $capturedCriteria);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createConfiguredRepo(callable $findFn): RepositoryInterface
    {
        return new class ($findFn) implements RepositoryInterface {
            public function __construct(private readonly \Closure $fn) {}
            public function find(ResourceId $id): ?array { return ($this->fn)($id); }
            public function insert(array $data): ResourceId { return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool { return true; }
            public function delete(ResourceId $id): bool { return true; }
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array { return []; }
            public function updateBulk(array $criteria, array $data): int { return 0; }
            public function deleteBulk(array $criteria): int { return 0; }
        };
    }

    private function createCapturingRepo(?int &$capturedLimit): RepositoryInterface
    {
        return new class ($capturedLimit) implements RepositoryInterface {
            public function __construct(private ?int &$captured) {}
            public function find(ResourceId $id): ?array { return null; }
            public function insert(array $data): ResourceId { return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool { return true; }
            public function delete(ResourceId $id): bool { return true; }
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array
            {
                $this->captured = $limit;

                return [];
            }
            public function updateBulk(array $criteria, array $data): int { return 0; }
            public function deleteBulk(array $criteria): int { return 0; }
        };
    }

    private function createCapturingRepoOffset(?int &$capturedOffset): RepositoryInterface
    {
        return new class ($capturedOffset) implements RepositoryInterface {
            public function __construct(private ?int &$captured) {}
            public function find(ResourceId $id): ?array { return null; }
            public function insert(array $data): ResourceId { return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool { return true; }
            public function delete(ResourceId $id): bool { return true; }
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array
            {
                $this->captured = $offset;

                return [];
            }
            public function updateBulk(array $criteria, array $data): int { return 0; }
            public function deleteBulk(array $criteria): int { return 0; }
        };
    }
}
