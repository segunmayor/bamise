<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\Policy;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Security\PolicyPortInterface;

final class CallablePolicy implements PolicyPortInterface
{
    /** @var callable(OperationType, ?object, string): bool */
    private $predicate;

    /**
     * @param callable(OperationType, ?object, string): bool $predicate
     */
    public function __construct(callable $predicate)
    {
        $this->predicate = $predicate;
    }

    public function allows(OperationType $operation, ?object $subject, string $resource): bool
    {
        return ($this->predicate)($operation, $subject, $resource);
    }
}
