<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\Audit;

readonly class AuditConfig
{
    /**
     * @param list<string> $redactFields Field names redacted in before/after payloads.
     */
    public function __construct(
        public array $redactFields = ['password', 'token', 'secret', 'authorization'],
    ) {
    }
}
