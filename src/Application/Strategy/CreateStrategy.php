<?php

declare(strict_types=1);

namespace Bamise\Application\Strategy;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Contract\Crud\OperationStrategyInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Domain\Service\FillableGuard;

final class CreateStrategy implements OperationStrategyInterface
{
    public function __construct(
        private readonly RepositoryResolver $repositories,
        private readonly ResourceRegistry $resources,
        private readonly FillableGuard $fillableGuard,
    ) {
    }

    public function execute(CrudContext $context): CrudResult
    {
        $definition = $this->resources->get($context->resourceName);
        $repository = $this->repositories->for($context->resourceName);
        $data = $this->fillableGuard->filter(
            $context->inputData,
            $definition->fillable(),
            $definition->guarded(),
        );
        $id = $repository->insert($data);

        return new CrudResult(
            success: true,
            data: array_merge($data, [$definition->primaryKey() => $id->value]),
            meta: ['operation' => $context->operation->value],
        );
    }
}
