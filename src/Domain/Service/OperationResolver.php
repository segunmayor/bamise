<?php

declare(strict_types=1);

namespace Bamise\Domain\Service;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Exception\OperationResolutionException;
use Bamise\Contract\Http\CrudRequestInterface;
use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Contract\ValueObject\RouteOperationConfig;
use Bamise\Domain\Model\Resource;
use Bamise\Domain\Model\ResolvedOperation;

/**
 * Resolves the CRUD operation for an incoming request using a strict server-side
 * priority chain.  Client-supplied values are suggestions only and may never
 * override server rules.
 *
 * Resolution priority (highest to lowest):
 *   1. RouteOperationConfig::pin()   — server pins a single operation; nothing else consulted.
 *   2. HTTP method                   — authoritative mapping of verb → OperationType.
 *   3. RouteOperationConfig::allow() — server's permitted set; narrows the candidate.
 *   4. Client hint                   — _crud_operation / data-bamise-crud-op; accepted only
 *                                      when it is both HTTP-method-compatible AND within the
 *                                      server-declared allowed set.
 */
final class OperationResolver
{
    private const string INPUT_HINT_KEY = '_crud_operation';

    private const string HEADER_HINT_KEY = 'data-bamise-crud-op';

    public function __construct(
        private readonly OperationTypeMapper $operationTypeMapper,
    ) {
    }

    public function resolve(
        CrudRequestInterface $request,
        Resource $resource,
        ?OperationType $defaultOperation = null,
        ?RouteOperationConfig $routeConfig = null,
    ): ResolvedOperation {
        $operation = $this->resolveOperationType($request, $defaultOperation, $routeConfig);
        $resourceId = $this->resolveResourceId($request, $resource, $operation);

        return new ResolvedOperation($operation, $resource, $resourceId);
    }

    // -------------------------------------------------------------------------
    // Operation resolution
    // -------------------------------------------------------------------------

    private function resolveOperationType(
        CrudRequestInterface $request,
        ?OperationType $defaultOperation,
        ?RouteOperationConfig $routeConfig,
    ): OperationType {
        // Priority 1 — server pin: authoritative, no client input consulted.
        if ($routeConfig !== null && $routeConfig->pinned !== null) {
            return $routeConfig->pinned;
        }

        // Priority 2 — HTTP method.
        $candidate = $this->operationTypeMapper->fromHttpMethod($request->method(), $defaultOperation);

        if ($candidate === null) {
            throw new OperationResolutionException(
                sprintf('Unable to resolve CRUD operation for HTTP method "%s".', $request->method()),
            );
        }

        // Priority 3 — Route config allowed set: validate and narrow the candidate.
        if ($routeConfig !== null && $routeConfig->isRestricted()) {
            $candidate = $this->applyAllowedSet($candidate, $request->method(), $routeConfig);
        }

        // Priority 4 — Client hint: suggestion only, validated against both HTTP method
        //              compatibility and the server-declared allowed set.
        $hint = $this->extractClientHint($request);

        if ($hint !== null) {
            $candidate = $this->applyClientHint($hint, $request->method(), $routeConfig);
        }

        return $candidate;
    }

    /**
     * Given the HTTP-method-resolved candidate, narrow it using the server's
     * allowed set.  The candidate must be within what the server permits.
     */
    private function applyAllowedSet(
        OperationType $candidate,
        string $method,
        RouteOperationConfig $routeConfig,
    ): OperationType {
        $compatible = $this->intersectWithMethod($routeConfig->allowed ?? [], $method);

        if ($compatible === []) {
            throw new OperationResolutionException(
                sprintf(
                    'Route configuration permits no operation compatible with HTTP method "%s".',
                    strtoupper($method),
                ),
            );
        }

        // If the HTTP-resolved candidate is explicitly permitted, keep it.
        if ($routeConfig->permits($candidate)) {
            return $candidate;
        }

        // The candidate is not in the allowed set, but exactly one compatible
        // operation is — use it (e.g. route allows [BulkDelete], HTTP says DELETE
        // which maps to Delete by default → promote to BulkDelete).
        if (count($compatible) === 1) {
            return $compatible[0];
        }

        // Multiple compatible operations are allowed but the default candidate is
        // not among them.  A client hint (priority 4) must disambiguate.
        // Return the first compatible entry as a provisional answer;
        // applyClientHint() will replace it if a valid hint is present.
        return $compatible[0];
    }

    /**
     * Validate and apply a client hint.  The hint is rejected if it:
     *   (a) is not compatible with the HTTP method, or
     *   (b) is not within the server-declared allowed set.
     */
    private function applyClientHint(
        OperationType $hint,
        string $method,
        ?RouteOperationConfig $routeConfig,
    ): OperationType {
        $compatibleGroup = $this->operationTypeMapper->compatibleOperations($method);

        if (! in_array($hint, $compatibleGroup, true)) {
            throw new OperationResolutionException(
                sprintf(
                    'Client hint "%s" is not compatible with HTTP method "%s".',
                    $hint->value,
                    strtoupper($method),
                ),
            );
        }

        if ($routeConfig !== null && ! $routeConfig->permits($hint)) {
            throw new OperationResolutionException(
                sprintf(
                    'Client hint "%s" is not within the server-permitted operation set.',
                    $hint->value,
                ),
            );
        }

        return $hint;
    }

    // -------------------------------------------------------------------------
    // Resource-ID resolution
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Extract the client hint from the request.  Returns null if absent or blank.
     * Throws if header and input conflict with each other.
     */
    private function extractClientHint(CrudRequestInterface $request): ?OperationType
    {
        $fromInput = $this->parseHint($request->input()[self::INPUT_HINT_KEY] ?? null);
        $fromHeader = $this->parseHint(
            $this->headerValue($request->headers(), self::HEADER_HINT_KEY),
        );

        if ($fromInput !== null && $fromHeader !== null && $fromInput !== $fromHeader) {
            throw new OperationResolutionException(
                'Ambiguous client hint: request body and header disagree on the operation.',
            );
        }

        return $fromInput ?? $fromHeader;
    }

    private function parseHint(mixed $value): ?OperationType
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) && ! is_int($value)) {
            throw new OperationResolutionException('Client operation hint must be a string.');
        }

        $operation = $this->operationTypeMapper->fromString((string) $value);

        if ($operation === null) {
            throw new OperationResolutionException(
                sprintf('Unknown client operation hint "%s".', (string) $value),
            );
        }

        return $operation;
    }

    /**
     * Returns the subset of $operations that are compatible with $method.
     *
     * @param list<OperationType> $operations
     * @return list<OperationType>
     */
    private function intersectWithMethod(array $operations, string $method): array
    {
        $compatible = $this->operationTypeMapper->compatibleOperations($method);

        return array_values(array_filter(
            $operations,
            static fn (OperationType $op): bool => in_array($op, $compatible, true),
        ));
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
}
