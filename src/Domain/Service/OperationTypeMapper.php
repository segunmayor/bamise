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

    /**
     * Returns every OperationType that is semantically valid for the given HTTP method.
     * This is the hard boundary that client hints cannot cross.
     *
     * @return list<OperationType>
     */
    public function compatibleOperations(string $method): array
    {
        return match (strtoupper($method)) {
            'GET'          => [OperationType::Read],
            'POST'         => [OperationType::Create],
            'PUT', 'PATCH' => [OperationType::Update, OperationType::BulkUpdate],
            'DELETE'       => [OperationType::Delete, OperationType::BulkDelete],
            default        => [],
        };
    }
}
