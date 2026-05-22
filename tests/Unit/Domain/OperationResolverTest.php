<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Domain;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Exception\OperationResolutionException;
use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Domain\Model\Resource;
use Bamise\Domain\Service\OperationResolver;
use Bamise\Domain\Service\OperationTypeMapper;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OperationResolverTest extends TestCase
{
    private OperationResolver $resolver;

    private Resource $resource;

    protected function setUp(): void
    {
        $this->resolver = new OperationResolver(new OperationTypeMapper());
        $this->resource = new Resource('users', 'users_table', 'id');
    }

    #[DataProvider('resolutionOrderProvider')]
    public function test_resolution_order(
        FakeCrudRequest $request,
        OperationType $expected,
        ?ResourceId $expectedId = null,
    ): void {
        $resolved = $this->resolver->resolve($request, $this->resource);

        self::assertSame($expected, $resolved->operation);
        self::assertSame($expectedId?->value, $resolved->resourceId?->value);
    }

    /**
     * @return iterable<string, array{FakeCrudRequest, OperationType, ?ResourceId}>
     */
    public static function resolutionOrderProvider(): iterable
    {
        yield 'input override wins over method' => [
            new FakeCrudRequest('POST', '/', ['_crud_operation' => 'read']),
            OperationType::Read,
        ];

        yield 'header override wins over method' => [
            (new FakeCrudRequest('POST'))
                ->withHeaders(['data-bamise-crud-op' => 'delete']),
            OperationType::Delete,
        ];

        yield 'input key wins over header' => [
            (new FakeCrudRequest('GET'))
                ->withInput(['_crud_operation' => 'create'])
                ->withHeaders(['data-bamise-crud-op' => 'delete']),
            OperationType::Create,
        ];

        yield 'POST maps to create' => [
            new FakeCrudRequest('POST'),
            OperationType::Create,
        ];

        yield 'GET maps to read' => [
            new FakeCrudRequest('GET'),
            OperationType::Read,
        ];

        yield 'PATCH maps to update with id' => [
            (new FakeCrudRequest('PATCH'))
                ->withInput(['id' => 42]),
            OperationType::Update,
            new ResourceId(42),
        ];
    }

    public function test_invalid_operation_throws(): void
    {
        $request = new FakeCrudRequest('GET', '/', ['_crud_operation' => 'not-a-real-op']);

        $this->expectException(OperationResolutionException::class);
        $this->resolver->resolve($request, $this->resource);
    }

    public function test_conflicting_header_and_input_override_throws(): void
    {
        $request = (new FakeCrudRequest('GET'))
            ->withInput(['data-bamise-crud-op' => 'create'])
            ->withHeaders(['data-bamise-crud-op' => 'delete']);

        $this->expectException(OperationResolutionException::class);
        $this->resolver->resolve($request, $this->resource);
    }

    public function test_unmappable_method_without_default_throws(): void
    {
        $request = new FakeCrudRequest('OPTIONS');

        $this->expectException(OperationResolutionException::class);
        $this->resolver->resolve($request, $this->resource);
    }

    public function test_default_operation_used_for_unknown_method(): void
    {
        $request = new FakeCrudRequest('OPTIONS');
        $resolved = $this->resolver->resolve($request, $this->resource, OperationType::Read);

        self::assertSame(OperationType::Read, $resolved->operation);
    }
}
