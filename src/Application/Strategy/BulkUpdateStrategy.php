<?php

declare(strict_types=1);

namespace Bamise\Application\Strategy;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Contract\Crud\OperationStrategyInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Domain\Service\FillableGuard;

final class BulkUpdateStrategy implements OperationStrategyInterface
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

        $rawCriteria = $context->inputData['_criteria'] ?? [];
        /** @var array<string, mixed> $criteria */
        $criteria = is_array($rawCriteria) ? $rawCriteria : [];

        $payload = $context->inputData;
        unset($payload['_criteria']);

        $data = $this->fillableGuard->filter(
            $payload,
            $definition->fillable(),
            $definition->guarded(),
        );

        if ($data === []) {
            return new CrudResult(
                success: false,
                errors: ['message' => 'No data provided for bulk update.'],
                meta: ['operation' => $context->operation->value],
            );
        }

        $affected = $repository->updateBulk($criteria, $data);

        return new CrudResult(
            success: true,
            data: ['affected' => $affected],
            meta: ['operation' => $context->operation->value],
        );
    }
}
