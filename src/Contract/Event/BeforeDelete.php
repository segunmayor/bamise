<?php

declare(strict_types=1);

namespace Bamise\Contract\Event;

use Bamise\Contract\ValueObject\CrudContext;

readonly class BeforeDelete implements DomainEventInterface
{
    /**
     * @param array<string, mixed>|null $payload
     */
    public function __construct(
        public CrudContext $context,
        public ?array $payload = null,
    ) {
    }
}
