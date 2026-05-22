<?php

declare(strict_types=1);

namespace Bamise\Application\Strategy;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Contract\Crud\OperationStrategyInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Contract\ValueObject\ResourceId;

final class DeleteStrategy implements OperationStrategyInterface
{
    public function __construct(
        private readonly RepositoryResolver $repositories,
        private readonly ResourceRegistry $resources,
    ) {
    }

    public function execute(CrudContext $context): CrudResult
    {
        $definition = $this->resources->get($context->resourceName);
        $repository = $this->repositories->for($context->resourceName);
        $primaryKey = $definition->primaryKey();
        $idValue = $context->inputData[$primaryKey]
            ?? $context->inputData['id']
            ?? null;

        if ($idValue === null || $idValue === '') {
            return new CrudResult(
                success: false,
                errors: ['message' => 'Resource not found'],
                meta: ['operation' => $context->operation->value],
            );
        }

        $deleted = $repository->delete(new ResourceId($idValue));

        if (! $deleted) {
            return new CrudResult(
                success: false,
                errors: ['message' => 'Resource not found'],
                meta: ['operation' => $context->operation->value],
            );
        }

        return new CrudResult(
            success: true,
            data: [$primaryKey => $idValue],
            meta: ['operation' => $context->operation->value],
        );
    }
}
