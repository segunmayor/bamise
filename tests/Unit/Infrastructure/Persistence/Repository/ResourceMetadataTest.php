<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Persistence\Repository;

use Bamise\Infrastructure\Persistence\Repository\ResourceMetadata;
use Bamise\Tests\Fixtures\TestUserResourceDefinition;
use PHPUnit\Framework\TestCase;

final class ResourceMetadataTest extends TestCase
{
    public function test_constructor_stores_values(): void
    {
        $meta = new ResourceMetadata('orders', 'order_id', ['product_id', 'qty']);

        self::assertSame('orders', $meta->table);
        self::assertSame('order_id', $meta->primaryKey);
        self::assertSame(['product_id', 'qty'], $meta->fillable);
    }

    public function test_from_resource_definition(): void
    {
        $definition = new TestUserResourceDefinition();
        $meta = ResourceMetadata::from($definition);

        self::assertSame('users', $meta->table);
        self::assertSame('id', $meta->primaryKey);
        self::assertSame(['name', 'email'], $meta->fillable);
    }

    public function test_from_with_empty_fillable(): void
    {
        $definition = new class implements \Bamise\Contract\Crud\ResourceDefinitionInterface {
            public function table(): string { return 't'; }
            public function primaryKey(): string { return 'pk'; }
            /** @return list<string> */
            public function fillable(): array { return []; }
            /** @return list<string> */
            public function guarded(): array { return []; }
            /** @return array<string, mixed> */
            public function rules(\Bamise\Contract\Enum\OperationType $operation): array { return []; }
            /** @return list<class-string> */
            public function policyClasses(): array { return []; }
        };

        $meta = ResourceMetadata::from($definition);

        self::assertSame([], $meta->fillable);
    }
}
