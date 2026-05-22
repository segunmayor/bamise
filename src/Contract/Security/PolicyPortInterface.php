<?php

declare(strict_types=1);

namespace Bamise\Contract\Security;

use Bamise\Contract\Enum\OperationType;

interface PolicyPortInterface
{
    public function allows(OperationType $operation, ?object $subject, string $resource): bool;
}
