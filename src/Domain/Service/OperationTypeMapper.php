<?php

declare(strict_types=1);

namespace Bamise\Domain\Service;

use Bamise\Contract\Enum\OperationType;

final class OperationTypeMapper
{
    public function fromHttpMethod(string $method, ?OperationType $override = null): ?OperationType
    {
        if ($override !== null) {
            return $override;
        }

        return match (strtoupper($method)) {
            'POST' => OperationType::Create,
            'GET' => OperationType::Read,
            'PUT', 'PATCH' => OperationType::Update,
            'DELETE' => OperationType::Delete,
            default => null,
        };
    }

    public function fromString(string $value): ?OperationType
    {
        $normalized = strtolower(trim($value));

        return OperationType::tryFrom($normalized);
    }
}
