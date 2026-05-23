<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Strategy;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Strategy\BulkDeleteStrategy;
use Bamise\Application\Strategy\BulkUpdateStrategy;
use Bamise\Application\Strategy\CreateStrategy;
use Bamise\Application\Strategy\DeleteStrategy;
use Bamise\Application\Strategy\OperationStrategyFactory;
use Bamise\Application\Strategy\ReadStrategy;
use Bamise\Application\Strategy\UpdateStrategy;
use Bamise\Contract\Crud\OperationStrategyInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Domain\Service\FillableGuard;
use Bamise\Tests\Fixtures\FakeRepository;
use Bamise\Tests\Fixtures\FakeResourceDefinition;
use PHPUnit\Framework\TestCase;

final class OperationStrategyFactoryTest extends TestCase
{
    public function test_for_create_returns_create_strategy(): void
    {
        self::assertInstanceOf(CreateStrategy::class, $this->factory()->for(OperationType::Create));
    }

    public function test_for_read_returns_read_strategy(): void
    {
        self::assertInstanceOf(ReadStrategy::class, $this->factory()->for(OperationType::Read));
    }

    public function test_for_update_returns_update_strategy(): void
    {
        self::assertInstanceOf(UpdateStrategy::class, $this->factory()->for(OperationType::Update));
    }

    public function test_for_delete_returns_delete_strategy(): void
    {
        self::assertInstanceOf(DeleteStrategy::class, $this->factory()->for(OperationType::Delete));
    }

    public function test_for_bulk_update_returns_bulk_update_strategy(): void
    {
        self::assertInstanceOf(BulkUpdateStrategy::class, $this->factory()->for(OperationType::BulkUpdate));
    }

    public function test_for_bulk_delete_returns_bulk_delete_strategy(): void
    {
        self::assertInstanceOf(BulkDeleteStrategy::class, $this->factory()->for(OperationType::BulkDelete));
    }

    public function test_custom_strategy_overrides_default(): void
    {
        $stub = new class implements OperationStrategyInterface {
            public function execute(CrudContext $context): CrudResult
            {
                return new CrudResult(success: true, data: ['custom' => true]);
            }
        };

        $factory = $this->factory([OperationType::Create->value => $stub]);

        self::assertSame($stub, $factory->for(OperationType::Create));
    }

    /**
     * @param array<string, OperationStrategyInterface> $overrides
     */
    private function factory(array $overrides = []): OperationStrategyFactory
    {
        $resources = new ResourceRegistry(['users' => new FakeResourceDefinition()]);
        $resolver = new RepositoryResolver(['users' => new FakeRepository()]);
        $guard = new FillableGuard();

        return new OperationStrategyFactory($resolver, $resources, $guard, $overrides ?: null);
    }
}
