<?php

declare(strict_types=1);

namespace Bamise\Application\Strategy;

use Bamise\Contract\Crud\OperationStrategyInterface;
use Bamise\Contract\Persistence\RepositoryInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;

final class ReadStrategy implements OperationStrategyInterface
{
    public function __construct(
        private readonly RepositoryInterface $repository,
    ) {
    }

    public function execute(CrudContext $context): CrudResult
    {
        return new CrudResult(
            success: false,
            errors: ['message' => 'Infrastructure not wired'],
            meta: ['operation' => $context->operation->value],
        );
    }
}
