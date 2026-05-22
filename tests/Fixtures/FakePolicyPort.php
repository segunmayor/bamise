<?php

declare(strict_types=1);

namespace Bamise\Tests\Fixtures;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Security\PolicyPortInterface;

final class FakePolicyPort implements PolicyPortInterface
{
    public function __construct(
        private bool $allowed = true,
    ) {
    }

    public function allows(OperationType $operation, ?object $subject, string $resource): bool
    {
        unset($operation, $subject, $resource);

        return $this->allowed;
    }
}
