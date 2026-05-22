<?php

declare(strict_types=1);

namespace Bamise\Domain\Service;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Exception\OperationResolutionException;
use Bamise\Contract\Http\CrudRequestInterface;
use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Domain\Model\Resource;
use Bamise\Domain\Model\ResolvedOperation;

final class OperationResolver
{
    private const string INPUT_OPERATION_KEY = '_crud_operation';

    private const string HEADER_OPERATION_KEY = 'data-bamise-crud-op';

    public function __construct(
        private readonly OperationTypeMapper $operationTypeMapper,
    ) {
    }

    public function resolve(
        CrudRequestInterface $request,
        Resource $resource,
        ?OperationType $defaultOperation = null,
    ): ResolvedOperation {
        $operation = $this->resolveOperationType($request, $defaultOperation);
        $resourceId = $this->resolveResourceId($request, $resource, $operation);

        return new ResolvedOperation($operation, $resource, $resourceId);
    }

    private function resolveOperationType(
        CrudRequestInterface $request,
        ?OperationType $defaultOperation,
    ): OperationType {
        $fromCrudInput = $this->extractOperationHint(
            $request->input()[self::INPUT_OPERATION_KEY] ?? null,
        );
        if ($fromCrudInput !== null) {
            return $fromCrudInput;
        }

        $fromOverride = $this->extractHeaderOrInputOverride($request);
        if ($fromOverride !== null) {
            return $fromOverride;
        }

        $fromMethod = $this->operationTypeMapper->fromHttpMethod(
            $request->method(),
            $defaultOperation,
        );
        if ($fromMethod !== null) {
            return $fromMethod;
        }

        throw new OperationResolutionException(
            sprintf(
                'Unable to resolve CRUD operation for HTTP method "%s".',
                $request->method(),
            ),
        );
    }

    private function extractHeaderOrInputOverride(CrudRequestInterface $request): ?OperationType
    {
        $headerValue = $this->headerValue($request->headers(), self::HEADER_OPERATION_KEY);
        $inputValue = $request->input()[self::HEADER_OPERATION_KEY] ?? null;

        if ($headerValue !== null && $inputValue !== null) {
            $headerOp = $this->extractOperationHint($headerValue);
            $inputOp = $this->extractOperationHint($inputValue);

            if ($headerOp !== $inputOp) {
                throw new OperationResolutionException(
                    'Ambiguous CRUD operation: header and input overrides disagree.',
                );
            }

            return $headerOp;
        }

        return $this->extractOperationHint($headerValue ?? $inputValue);
    }

    /**
     * @param array<string, list<string>|string> $headers
     */
    private function headerValue(array $headers, string $name): ?string
    {
        $normalized = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $normalized) {
                continue;
            }

            if (is_array($value)) {
                return $value[0] ?? null;
            }

            return (string) $value;
        }

        return null;
    }

    private function extractOperationHint(mixed $value): ?OperationType
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) && ! is_int($value)) {
            throw new OperationResolutionException('CRUD operation hint must be a string.');
        }

        $operation = $this->operationTypeMapper->fromString((string) $value);

        if ($operation === null) {
            throw new OperationResolutionException(
                sprintf('Invalid CRUD operation value "%s".', (string) $value),
            );
        }

        return $operation;
    }

    private function resolveResourceId(
        CrudRequestInterface $request,
        Resource $resource,
        OperationType $operation,
    ): ?ResourceId {
        $resolved = new ResolvedOperation($operation, $resource);
        if (! $resolved->requiresResourceId()) {
            return null;
        }

        $input = $request->input();
        $rawId = $input[$resource->primaryKey] ?? $input['id'] ?? null;

        if ($rawId === null || $rawId === '') {
            return null;
        }

        if (! is_string($rawId) && ! is_int($rawId)) {
            throw new OperationResolutionException(
                sprintf('Resource id for "%s" must be a string or integer.', $resource->primaryKey),
            );
        }

        return new ResourceId($rawId);
    }
}
