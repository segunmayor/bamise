<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Strategy;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Strategy\BulkUpdateStrategy;
use Bamise\Application\Strategy\DeleteStrategy;
use Bamise\Application\Strategy\UpdateStrategy;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Persistence\RepositoryInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Domain\Service\FillableGuard;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakeResourceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for UpdateStrategy, DeleteStrategy, BulkUpdateStrategy.
 *
 * Kills escaped mutants:
 * - UpdateStrategy Line 30: Coalesce on primaryKey vs 'id' fallback
 * - UpdateStrategy Lines 38,54,55,62: ArrayItem/ArrayItemRemoval in result arrays
 * - DeleteStrategy Line 28: Coalesce
 * - DeleteStrategy Lines 36,45,46: ArrayItem/ArrayItemRemoval
 * - BulkUpdateStrategy Line 46: ArrayItem/ArrayItemRemoval on errors key
 */
final class UpdateDeleteStrategyMutationTest extends TestCase
{
    private ResourceRegistry $resources;
    private RepositoryResolver $repos;
    private FakeResourceDefinition $definition;
    private FillableGuard $guard;

    protected function setUp(): void
    {
        $this->resources = new ResourceRegistry();
        $this->repos = new RepositoryResolver();
        $this->definition = new FakeResourceDefinition(
            table: 'products',
            primaryKey: 'id',
            fillable: ['name', 'price'],
            guarded: ['id'],
        );
        $this->resources->register('products', $this->definition);
        $this->guard = new FillableGuard();
    }

    private function updateStrategy(): UpdateStrategy
    {
        return new UpdateStrategy($this->repos, $this->resources, $this->guard);
    }

    private function deleteStrategy(): DeleteStrategy
    {
        return new DeleteStrategy($this->repos, $this->resources);
    }

    private function bulkUpdateStrategy(): BulkUpdateStrategy
    {
        return new BulkUpdateStrategy($this->repos, $this->resources, $this->guard);
    }

    private function context(OperationType $op, array $inputData = []): CrudContext
    {
        return new CrudContext($op, 'products', $inputData, null, new FakeCrudRequest());
    }

    private function updatingRepo(bool $success): RepositoryInterface
    {
        return new class ($success) implements RepositoryInterface {
            public function __construct(private bool $s)
            {
            }
            public function find(ResourceId $id): ?array
            {
                return null;
            }
            public function insert(array $data): ResourceId
            {
                return new ResourceId(1);
            }
            public function update(ResourceId $id, array $data): bool
            {
                return $this->s;
            }
            public function delete(ResourceId $id): bool
            {
                return $this->s;
            }
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array
            {
                return [];
            }
            public function updateBulk(array $criteria, array $data): int
            {
                return count($data);
            }
            public function deleteBulk(array $criteria): int
            {
                return 2;
            }
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UpdateStrategy
    // ─────────────────────────────────────────────────────────────────────────

    // ── Line 30: Coalesce — primaryKey vs 'id' fallback ──────────────────────

    public function test_update_uses_primary_key_field(): void
    {
        $definition = new FakeResourceDefinition(primaryKey: 'product_id', fillable: ['name'], guarded: ['product_id']);
        $this->resources->register('products', $definition);
        $this->repos->register('products', $this->updatingRepo(true));

        $result = $this->updateStrategy()->execute(
            $this->context(OperationType::Update, ['product_id' => 5, 'name' => 'Widget']),
        );

        self::assertTrue($result->success);
    }

    public function test_update_falls_back_to_id_field(): void
    {
        $this->repos->register('products', $this->updatingRepo(true));

        $result = $this->updateStrategy()->execute(
            $this->context(OperationType::Update, ['id' => 5, 'name' => 'Widget']),
        );

        self::assertTrue($result->success);
    }

    // ── Line 38: ArrayItem/ArrayItemRemoval — errors key in missing-id path ──

    public function test_update_without_valid_id_returns_error_with_message_key(): void
    {
        $this->repos->register('products', $this->updatingRepo(true));

        $result = $this->updateStrategy()->execute(
            $this->context(OperationType::Update, ['name' => 'Widget']),
        );

        self::assertFalse($result->success);
        self::assertArrayHasKey('message', $result->errors);
        self::assertSame('Resource not found', $result->errors['message']);
    }

    public function test_update_without_id_has_operation_in_meta(): void
    {
        $this->repos->register('products', $this->updatingRepo(true));

        $result = $this->updateStrategy()->execute(
            $this->context(OperationType::Update, ['name' => 'Widget']),
        );

        self::assertArrayHasKey('operation', $result->meta);
        self::assertSame('update', $result->meta['operation']);
    }

    // ── Lines 54,55: ArrayItem/ArrayItemRemoval — not-updated path ───────────

    public function test_update_not_found_returns_error_with_message_key(): void
    {
        $this->repos->register('products', $this->updatingRepo(false));

        $result = $this->updateStrategy()->execute(
            $this->context(OperationType::Update, ['id' => 999, 'name' => 'Widget']),
        );

        self::assertFalse($result->success);
        self::assertArrayHasKey('message', $result->errors);
        self::assertSame('Resource not found', $result->errors['message']);
    }

    public function test_update_not_found_has_operation_in_meta(): void
    {
        $this->repos->register('products', $this->updatingRepo(false));

        $result = $this->updateStrategy()->execute(
            $this->context(OperationType::Update, ['id' => 999, 'name' => 'Widget']),
        );

        self::assertArrayHasKey('operation', $result->meta);
    }

    // ── Line 62: ArrayItem/ArrayItemRemoval — success path data ─────────────

    public function test_update_success_includes_primary_key_in_data(): void
    {
        $this->repos->register('products', $this->updatingRepo(true));

        $result = $this->updateStrategy()->execute(
            $this->context(OperationType::Update, ['id' => 7, 'name' => 'Widget']),
        );

        self::assertTrue($result->success);
        self::assertArrayHasKey('id', $result->data);
        self::assertSame(7, $result->data['id']);
    }

    public function test_update_success_includes_updated_fields(): void
    {
        $this->repos->register('products', $this->updatingRepo(true));

        $result = $this->updateStrategy()->execute(
            $this->context(OperationType::Update, ['id' => 7, 'name' => 'NewName']),
        );

        self::assertArrayHasKey('name', $result->data);
        self::assertSame('NewName', $result->data['name']);
    }

    public function test_update_success_has_operation_in_meta(): void
    {
        $this->repos->register('products', $this->updatingRepo(true));

        $result = $this->updateStrategy()->execute(
            $this->context(OperationType::Update, ['id' => 7, 'name' => 'X']),
        );

        self::assertArrayHasKey('operation', $result->meta);
        self::assertSame('update', $result->meta['operation']);
    }

    // ── Array id type check ───────────────────────────────────────────────────

    public function test_update_array_id_returns_not_found(): void
    {
        $this->repos->register('products', $this->updatingRepo(true));

        $result = $this->updateStrategy()->execute(
            $this->context(OperationType::Update, ['id' => ['array']]),
        );

        self::assertFalse($result->success);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DeleteStrategy
    // ─────────────────────────────────────────────────────────────────────────

    // ── Line 28: Coalesce ─────────────────────────────────────────────────────

    public function test_delete_uses_primary_key_field(): void
    {
        $definition = new FakeResourceDefinition(primaryKey: 'item_id', fillable: [], guarded: ['item_id']);
        $this->resources->register('products', $definition);
        $this->repos->register('products', $this->updatingRepo(true));

        $result = $this->deleteStrategy()->execute(
            $this->context(OperationType::Delete, ['item_id' => 3]),
        );

        self::assertTrue($result->success);
    }

    public function test_delete_falls_back_to_id_field(): void
    {
        $this->repos->register('products', $this->updatingRepo(true));

        $result = $this->deleteStrategy()->execute(
            $this->context(OperationType::Delete, ['id' => 3]),
        );

        self::assertTrue($result->success);
    }

    // ── Line 36: ArrayItem/ArrayItemRemoval — missing id path ────────────────

    public function test_delete_without_valid_id_returns_error_with_message_key(): void
    {
        $this->repos->register('products', $this->updatingRepo(true));

        $result = $this->deleteStrategy()->execute(
            $this->context(OperationType::Delete, []),
        );

        self::assertFalse($result->success);
        self::assertArrayHasKey('message', $result->errors);
        self::assertSame('Resource not found', $result->errors['message']);
    }

    public function test_delete_without_id_has_operation_in_meta(): void
    {
        $this->repos->register('products', $this->updatingRepo(true));

        $result = $this->deleteStrategy()->execute(
            $this->context(OperationType::Delete, []),
        );

        self::assertArrayHasKey('operation', $result->meta);
    }

    // ── Lines 45,46: ArrayItem/ArrayItemRemoval — not-deleted path ───────────

    public function test_delete_not_found_has_message_in_errors(): void
    {
        $this->repos->register('products', $this->updatingRepo(false));

        $result = $this->deleteStrategy()->execute(
            $this->context(OperationType::Delete, ['id' => 42]),
        );

        self::assertFalse($result->success);
        self::assertArrayHasKey('message', $result->errors);
        self::assertSame('Resource not found', $result->errors['message']);
    }

    public function test_delete_not_found_has_operation_in_meta(): void
    {
        $this->repos->register('products', $this->updatingRepo(false));

        $result = $this->deleteStrategy()->execute(
            $this->context(OperationType::Delete, ['id' => 42]),
        );

        self::assertArrayHasKey('operation', $result->meta);
        self::assertSame('delete', $result->meta['operation']);
    }

    // ── Success path ─────────────────────────────────────────────────────────

    public function test_delete_success_includes_id_in_data(): void
    {
        $this->repos->register('products', $this->updatingRepo(true));

        $result = $this->deleteStrategy()->execute(
            $this->context(OperationType::Delete, ['id' => 5]),
        );

        self::assertTrue($result->success);
        self::assertArrayHasKey('id', $result->data);
        self::assertSame(5, $result->data['id']);
    }

    public function test_delete_success_has_operation_in_meta(): void
    {
        $this->repos->register('products', $this->updatingRepo(true));

        $result = $this->deleteStrategy()->execute(
            $this->context(OperationType::Delete, ['id' => 5]),
        );

        self::assertArrayHasKey('operation', $result->meta);
        self::assertSame('delete', $result->meta['operation']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BulkUpdateStrategy
    // ─────────────────────────────────────────────────────────────────────────

    // ── Line 46: ArrayItem/ArrayItemRemoval on errors['message'] ─────────────

    public function test_bulk_update_with_no_fillable_data_returns_error_with_message_key(): void
    {
        $this->repos->register('products', $this->updatingRepo(true));

        // Only guarded fields → after filter, data is empty
        $result = $this->bulkUpdateStrategy()->execute(
            $this->context(OperationType::BulkUpdate, ['id' => 1]), // 'id' is guarded
        );

        self::assertFalse($result->success);
        self::assertArrayHasKey('message', $result->errors);
        self::assertSame('No data provided for bulk update.', $result->errors['message']);
    }

    public function test_bulk_update_with_no_fillable_data_has_operation_in_meta(): void
    {
        $this->repos->register('products', $this->updatingRepo(true));

        $result = $this->bulkUpdateStrategy()->execute(
            $this->context(OperationType::BulkUpdate, ['id' => 1]),
        );

        self::assertArrayHasKey('operation', $result->meta);
    }

    public function test_bulk_update_success_includes_affected_in_data(): void
    {
        $this->repos->register('products', $this->updatingRepo(true));

        $result = $this->bulkUpdateStrategy()->execute(
            $this->context(OperationType::BulkUpdate, ['name' => 'Widget']),
        );

        self::assertTrue($result->success);
        self::assertArrayHasKey('affected', $result->data);
    }

    public function test_bulk_update_with_criteria_uses_it(): void
    {
        $this->repos->register('products', $this->updatingRepo(true));

        $result = $this->bulkUpdateStrategy()->execute(
            $this->context(OperationType::BulkUpdate, [
                '_criteria' => ['category' => 'electronics'],
                'name' => 'Updated',
            ]),
        );

        self::assertTrue($result->success);
    }
}
