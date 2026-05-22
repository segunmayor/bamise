<?php

declare(strict_types=1);

namespace Bamise\Domain\Model;

use InvalidArgumentException;

readonly class Permission
{
    public function __construct(
        public string $resource,
        public string $action,
    ) {
    }

    public static function fromString(string $permission): self
    {
        $parts = explode('.', $permission, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidArgumentException(
                sprintf('Permission must be in "resource.action" format, got "%s".', $permission),
            );
        }

        return new self($parts[0], $parts[1]);
    }

    public function toString(): string
    {
        return $this->resource . '.' . $this->action;
    }
}
