<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\Policy;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Security\PolicyPortInterface;

final class PolicyChain implements PolicyPortInterface
{
    /** @var list<PolicyPortInterface> */
    private array $policies;

    /**
     * @param PolicyPortInterface ...$policies All policies must allow (AND semantics).
     */
    public function __construct(PolicyPortInterface ...$policies)
    {
        $this->policies = array_values($policies);
    }

    #[\Override]
    public function allows(OperationType $operation, ?object $subject, string $resource): bool
    {
        foreach ($this->policies as $policy) {
            if (! $policy->allows($operation, $subject, $resource)) {
                return false;
            }
        }

        return $this->policies !== [];
    }
}
