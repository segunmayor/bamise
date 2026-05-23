<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Domain;

use Bamise\Contract\Enum\OperationType;
use Bamise\Domain\Model\ResolvedOperation;
use Bamise\Domain\Model\Resource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResolvedOperationTest extends TestCase
{
    #[DataProvider('requiresIdOperations')]
    public function test_requires_resource_id_for_single_record_mutations(OperationType $operation): void
    {
        $resolved = new ResolvedOperation($operation, $this->resource());

        self::assertTrue($resolved->requiresResourceId());
    }

    #[DataProvider('doesNotRequireIdOperations')]
    public function test_does_not_require_resource_id_for_collection_and_read_operations(OperationType $operation): void
    {
        $resolved = new ResolvedOperation($operation, $this->resource());

        self::assertFalse($resolved->requiresResourceId());
    }

    /**
     * @return list<array{OperationType}>
     */
    public static function requiresIdOperations(): array
    {
        return [
            [OperationType::Update],
            [OperationType::Delete],
        ];
    }

    /**
     * @return list<array{OperationType}>
     */
    public static function doesNotRequireIdOperations(): array
    {
        return [
            [OperationType::Create],
            [OperationType::Read],
            [OperationType::BulkUpdate],
            [OperationType::BulkDelete],
        ];
    }

    private function resource(): Resource
    {
        return new Resource('users', 'users', 'id');
    }
}
