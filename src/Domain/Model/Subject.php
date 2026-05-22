<?php

declare(strict_types=1);

namespace Bamise\Domain\Model;

readonly class Subject
{
    /**
     * @param list<string> $roles
     * @param list<string> $permissions Explicit permission strings (e.g. users.create)
     */
    public function __construct(
        public string|int $id,
        public array $roles = [],
        public array $permissions = [],
    ) {
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
