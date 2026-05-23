<?php

declare(strict_types=1);

namespace Bamise\Domain\Model;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\ResourceId;

readonly class ResolvedOperation
{
    public function __construct(
        public OperationType $operation,
        public Resource $resource,
        public ?ResourceId $resourceId = null,
    ) {
    }

    public function requiresResourceId(): bool
    {
        return match ($this->operation) {
            OperationType::Update,
            OperationType::Delete => true,
            default => false,
        };
    }
}
