<?php

declare(strict_types=1);

namespace Bamise\Application\Strategy;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Contract\Crud\OperationStrategyInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;

final class BulkDeleteStrategy implements OperationStrategyInterface
{
    public function __construct(
        private readonly RepositoryResolver $repositories,
    ) {
    }

    #[\Override]
    public function execute(CrudContext $context): CrudResult
    {
        $repository = $this->repositories->for($context->resourceName);

        $rawCriteria = $context->inputData['_criteria'] ?? [];
        /** @var array<string, mixed> $criteria */
        $criteria = is_array($rawCriteria) ? $rawCriteria : [];

        $affected = $repository->deleteBulk($criteria);

        return new CrudResult(
            success: true,
            data: ['affected' => $affected],
            meta: ['operation' => $context->operation->value],
        );
    }
}
