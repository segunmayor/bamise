<?php

declare(strict_types=1);

namespace Bamise\Application\Handler;

use Bamise\Contract\Crud\OperationStrategyFactoryInterface;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;

final class StrategyDispatchHandler implements CrudHandlerInterface
{
    public function __construct(
        private readonly OperationStrategyFactoryInterface $strategyFactory,
    ) {
    }

    #[\Override]
    public function handle(CrudContext $context): CrudResult
    {
        return $this->strategyFactory
            ->for($context->operation)
            ->execute($context);
    }
}
