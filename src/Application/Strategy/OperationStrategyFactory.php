<?php

declare(strict_types=1);

namespace Bamise\Application\Strategy;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Contract\Crud\OperationStrategyFactoryInterface;
use Bamise\Contract\Crud\OperationStrategyInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Domain\Service\FillableGuard;
use InvalidArgumentException;

final class OperationStrategyFactory implements OperationStrategyFactoryInterface
{
    /** @var array<string, OperationStrategyInterface> */
    private array $strategies;

    /**
     * @param array<string, OperationStrategyInterface>|iterable<OperationType, OperationStrategyInterface> $strategies
     */
    public function __construct(
        RepositoryResolver $repositories,
        ResourceRegistry $resources,
        FillableGuard $fillableGuard,
        ?iterable $strategies = null,
    ) {
        $this->strategies = [
            OperationType::Create->value => new CreateStrategy($repositories, $resources, $fillableGuard),
            OperationType::Read->value => new ReadStrategy($repositories, $resources),
            OperationType::Update->value => new UpdateStrategy($repositories, $resources, $fillableGuard),
            OperationType::Delete->value => new DeleteStrategy($repositories, $resources),
            OperationType::BulkUpdate->value => new UpdateStrategy($repositories, $resources, $fillableGuard),
            OperationType::BulkDelete->value => new DeleteStrategy($repositories, $resources),
        ];

        if ($strategies !== null) {
            foreach ($strategies as $operation => $strategy) {
                $key = $operation instanceof OperationType
                    ? $operation->value
                    : (string) $operation;
                $this->strategies[$key] = $strategy;
            }
        }
    }

    public function for(OperationType $operation): OperationStrategyInterface
    {
        if (! isset($this->strategies[$operation->value])) {
            throw new InvalidArgumentException(
                sprintf('No strategy registered for operation "%s".', $operation->value),
            );
        }

        return $this->strategies[$operation->value];
    }
}
