<?php

declare(strict_types=1);

namespace Bamise\Application\Strategy;

use Bamise\Contract\Crud\OperationStrategyFactoryInterface;
use Bamise\Contract\Crud\OperationStrategyInterface;
use Bamise\Contract\Enum\OperationType;
use InvalidArgumentException;

final class OperationStrategyFactory implements OperationStrategyFactoryInterface
{
    /** @var array<string, OperationStrategyInterface> */
    private array $strategies;

    /**
     * @param array<string, OperationStrategyInterface>|iterable<OperationType, OperationStrategyInterface> $strategies
     */
    public function __construct(
        CreateStrategy $create,
        ReadStrategy $read,
        UpdateStrategy $update,
        DeleteStrategy $delete,
        ?iterable $strategies = null,
    ) {
        $this->strategies = [
            OperationType::Create->value => $create,
            OperationType::Read->value => $read,
            OperationType::Update->value => $update,
            OperationType::Delete->value => $delete,
            OperationType::BulkUpdate->value => $update,
            OperationType::BulkDelete->value => $delete,
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
