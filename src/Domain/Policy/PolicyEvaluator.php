<?php

declare(strict_types=1);

namespace Bamise\Domain\Policy;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Security\PolicyPortInterface;
use Bamise\Domain\Model\Subject;
use Bamise\Domain\Service\OperationTypeMapper;

final class PolicyEvaluator
{
    public function __construct(
        private readonly PolicyPortInterface $policyPort,
        private readonly OperationTypeMapper $operationTypeMapper,
    ) {
    }

    /**
     * @param array<string, mixed>|null $target
     */
    public function evaluate(
        Subject $subject,
        string $action,
        string $resource,
        ?array $target = null,
    ): bool {
        unset($target);

        $operation = $this->operationTypeMapper->fromString($action);
        if ($operation === null) {
            return false;
        }

        return $this->policyPort->allows($operation, $subject, $resource);
    }
}
