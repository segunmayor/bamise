<?php

declare(strict_types=1);

namespace Bamise\Application\Context;

/**
 * Simple DTO for infrastructure adapters that return plain auth payloads.
 */
readonly class AuthSubjectDto
{
    /**
     * @param list<string> $roles
     * @param list<string> $permissions
     */
    public function __construct(
        public string|int $id,
        public array $roles = [],
        public array $permissions = [],
    ) {
    }
}
