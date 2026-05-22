<?php

declare(strict_types=1);

namespace Bamise\Domain\Service;

use Bamise\Domain\Model\Permission;
use Bamise\Domain\Model\Subject;

final class PermissionEvaluator
{
    /**
     * @param array<string, mixed>|null $target
     */
    public function isGranted(Subject $subject, Permission $permission, ?array $target = null): bool
    {
        unset($target);

        return $subject->hasPermission($permission->toString());
    }
}
