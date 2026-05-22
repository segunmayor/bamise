<?php

declare(strict_types=1);

namespace Bamise\Contract\ValueObject;

readonly class AuditRecord
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function __construct(
        public ?string $actor,
        public string $action,
        public string $resource,
        public ResourceId|string|int|null $recordId,
        public ?string $ip,
        public ?string $userAgent,
        public ?array $before = null,
        public ?array $after = null,
        public ?string $correlationId = null,
    ) {
    }
}
