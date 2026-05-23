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

        if ($idValue !== null && $idValue !== '') {
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

        $reserved = [$primaryKey, 'id', 'limit', 'offset'];
        $criteria = $this->extractCriteria($context->inputData, $reserved);
        $limit = is_numeric($context->inputData['limit'] ?? null)
            ? max(1, (int) $context->inputData['limit'])
            : 100;
        $offset = is_numeric($context->inputData['offset'] ?? null)
            ? max(0, (int) $context->inputData['offset'])
            : 0;

        $rows = $repository->findAll($criteria, $limit, $offset);

        return new CrudResult(
            success: true,
            data: ['items' => $rows],
            meta: ['operation' => $context->operation->value, 'count' => count($rows)],
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

    /**
     * @param array<string, mixed> $inputData
     * @param list<string>         $exclude
     *
     * @return array<string, mixed>
     */
    private function extractCriteria(array $inputData, array $exclude): array
    {
        $criteria = [];

        foreach ($inputData as $key => $value) {
            if (! in_array($key, $exclude, true)) {
                $criteria[$key] = $value;
            }
        }

        return $criteria;
    }
}
