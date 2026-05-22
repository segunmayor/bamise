<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\Policy;

/**
 * Resource-scoped policy classes referenced from ResourceDefinitionInterface::policyClasses().
 */
interface PolicyInterface
{
    public function allows(object $subject, string $action, string $resource, mixed $target = null): bool;
}
