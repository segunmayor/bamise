<?php

declare(strict_types=1);

namespace Bamise\Application\Strategy;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Contract\Crud\OperationStrategyInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Contract\ValueObject\ResourceId;

final class ReadStrategy implements OperationStrategyInterface
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
            return $this->notFound($context);
        }

        $row = $repository->find(new ResourceId($idValue));

        if ($row === null) {
            return $this->notFound($context);
        }

        return new CrudResult(
            success: true,
            data: $row,
            meta: ['operation' => $context->operation->value],
        );
    }

    private function notFound(CrudContext $context): CrudResult
    {
        return new CrudResult(
            success: false,
            errors: ['message' => 'Resource not found'],
            meta: ['operation' => $context->operation->value],
        );
    }
}
