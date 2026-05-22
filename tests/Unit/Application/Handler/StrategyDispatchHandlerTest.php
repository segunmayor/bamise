<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Handler;

use Bamise\Application\Handler\StrategyDispatchHandler;
use Bamise\Contract\Crud\OperationStrategyFactoryInterface;
use Bamise\Contract\Crud\OperationStrategyInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class StrategyDispatchHandlerTest extends TestCase
{
    public function test_delegates_to_strategy_from_factory(): void
    {
        $expected = new CrudResult(success: true, data: ['id' => 1]);
        $strategy = new class ($expected) implements OperationStrategyInterface {
            public function __construct(private CrudResult $result) {}
            public function execute(CrudContext $context): CrudResult { return $this->result; }
        };

        $factory = new class ($strategy) implements OperationStrategyFactoryInterface {
            public function __construct(private OperationStrategyInterface $strategy) {}
            public function for(OperationType $operation): OperationStrategyInterface { return $this->strategy; }
        };

        $handler = new StrategyDispatchHandler($factory);
        $context = new CrudContext(
            OperationType::Create,
            'users',
            ['name' => 'Ada'],
            null,
            new FakeCrudRequest('POST', '/users'),
        );

        $result = $handler->handle($context);

        self::assertSame($expected, $result);
    }

    public function test_passes_context_to_strategy(): void
    {
        $capturedContext = null;
        $strategy = new class ($capturedContext) implements OperationStrategyInterface {
            public function __construct(private mixed &$capturedContext) {}
            public function execute(CrudContext $context): CrudResult
            {
                $this->capturedContext = $context;
                return new CrudResult(success: true);
            }
        };

        $factory = new class ($strategy) implements OperationStrategyFactoryInterface {
            public function __construct(private OperationStrategyInterface $strategy) {}
            public function for(OperationType $operation): OperationStrategyInterface { return $this->strategy; }
        };

        $context = new CrudContext(
            OperationType::Read,
            'orders',
            ['id' => 7],
            null,
            new FakeCrudRequest('GET', '/orders'),
        );

        (new StrategyDispatchHandler($factory))->handle($context);

        self::assertSame($context, $capturedContext);
    }
}
