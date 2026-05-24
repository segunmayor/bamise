<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\Policy;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Security\PolicyPortInterface;
use InvalidArgumentException;

final class ClassPolicyAdapter implements PolicyPortInterface
{
    /** @var list<class-string> */
    private array $policyClasses;

    /**
     * @param list<class-string> $policyClasses
     */
    public function __construct(
        array $policyClasses,
        private readonly ?object $target = null,
    ) {
        $this->policyClasses = $policyClasses;
    }

    #[\Override]
    public function allows(OperationType $operation, ?object $subject, string $resource): bool
    {
        if ($subject === null) {
            return false;
        }

        foreach ($this->policyClasses as $className) {
            $policy = $this->instantiate($className);

            if ($policy instanceof PolicyPortInterface) {
                if (! $policy->allows($operation, $subject, $resource)) {
                    return false;
                }

                continue;
            }

            if ($policy instanceof PolicyInterface) {
                if (! $policy->allows($subject, $operation->value, $resource, $this->target)) {
                    return false;
                }

                continue;
            }

            throw new InvalidArgumentException(
                sprintf('Policy class "%s" must implement PolicyInterface or PolicyPortInterface.', $className),
            );
        }

        return $this->policyClasses !== [];
    }

    private function instantiate(string $className): object
    {
        if (! class_exists($className)) {
            throw new InvalidArgumentException(sprintf('Policy class "%s" does not exist.', $className));
        }

        return new $className();
    }
}
