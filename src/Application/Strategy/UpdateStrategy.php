<?php

declare(strict_types=1);

namespace Bamise\Application\Strategy;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Contract\Crud\OperationStrategyInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Domain\Service\FillableGuard;

final class UpdateStrategy implements OperationStrategyInterface
{
    public function __construct(
        private readonly RepositoryResolver $repositories,
        private readonly ResourceRegistry $resources,
        private readonly FillableGuard $fillableGuard,
    ) {
    }

    #[\Override]
    public function execute(CrudContext $context): CrudResult
    {
        $definition = $this->resources->get($context->resourceName);
        $repository = $this->repositories->for($context->resourceName);
        $primaryKey = $definition->primaryKey();
        $raw = $context->inputData[$primaryKey]
            ?? $context->inputData['id']
            ?? null;

        if (! is_int($raw) && ! is_string($raw)) {
            return new CrudResult(
                success: false,
                errors: ['message' => 'Resource not found'],
                meta: ['operation' => $context->operation->value],
            );
        }

        $data = $this->fillableGuard->filter(
            $context->inputData,
            $definition->fillable(),
            $definition->guarded(),
        );
        unset($data[$primaryKey]);

        $updated = $repository->update(new ResourceId($raw), $data);

        if (! $updated) {
            return new CrudResult(
                success: false,
                errors: ['message' => 'Resource not found'],
                meta: ['operation' => $context->operation->value],
            );
        }

        return new CrudResult(
            success: true,
            data: array_merge([$primaryKey => $raw], $data),
            meta: ['operation' => $context->operation->value],
        );
    }
}
