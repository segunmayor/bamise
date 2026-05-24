<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Domain;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Exception\OperationResolutionException;
use Bamise\Contract\ValueObject\RouteOperationConfig;
use Bamise\Domain\Model\Resource;
use Bamise\Domain\Service\OperationResolver;
use Bamise\Domain\Service\OperationTypeMapper;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for OperationResolver.
 *
 * Kills escaped mutants:
 * - Line 105: UnwrapStrToUpper in applyAllowedSet error message
 * - Line 126: IncrementInteger on $compatible[0]
 * - Line 146: UnwrapStrToUpper in applyClientHint error message
 * - Lines 185,186: LogicalNot/LogicalAndAllSubExprNegation/Throw_ on rawId type check
 * - Lines 224,225: LogicalNot/LogicalAndAllSubExprNegation/Throw_ on parseHint type check
 * - Line 228: CastString in fromString call
 * - Line 232: CastString in unknown hint error message
 * - Line 249: UnwrapArrayValues in intersectWithMethod
 * - Lines 260,263: UnwrapStrToLower/CastString in headerValue
 * - Lines 264/268: Continue_/IncrementInteger in headerValue loop
 */
final class OperationResolverMutationTest extends TestCase
{
    private OperationResolver $resolver;
    private Resource $resource;

    protected function setUp(): void
    {
        $this->resolver = new OperationResolver(new OperationTypeMapper());
        $this->resource = new Resource('users', 'users_table', 'id');
    }

    // ── Line 105: strtoupper in incompatible allowed-set error ────────────────

    public function test_incompatible_allowed_set_error_message_has_uppercase_method(): void
    {
        $this->expectException(OperationResolutionException::class);
        $this->expectExceptionMessageMatches('/GET/');

        $this->resolver->resolve(
            new FakeCrudRequest('get'),
            $this->resource,
            null,
            RouteOperationConfig::allow(OperationType::Create),
        );
    }

    // ── Line 126: IncrementInteger on $compatible[0] fallback ────────────────

    public function test_first_compatible_operation_returned_when_candidate_not_in_allowed(): void
    {
        // PATCH is compatible with [Update, BulkUpdate]
        // allowed = [BulkUpdate] → candidate=Update not permitted → returns $compatible[0]=BulkUpdate
        $resolved = $this->resolver->resolve(
            new FakeCrudRequest('PATCH'),
            $this->resource,
            null,
            RouteOperationConfig::allow(OperationType::BulkUpdate),
        );

        self::assertSame(OperationType::BulkUpdate, $resolved->operation);
    }

    // ── Line 146: strtoupper in applyClientHint error message ────────────────

    public function test_incompatible_client_hint_error_has_uppercase_method(): void
    {
        $request = (new FakeCrudRequest('get'))
            ->withInput(['_crud_operation' => 'delete']);

        $this->expectException(OperationResolutionException::class);
        $this->expectExceptionMessageMatches('/GET/');

        $this->resolver->resolve($request, $this->resource);
    }

    // ── Lines 185,186: rawId type check + Throw_ ──────────────────────────────

    public function test_array_raw_id_throws_operation_resolution_exception(): void
    {
        // Requires Update (PUT/PATCH) to enter requiresResourceId() = true path
        $request = (new FakeCrudRequest('PUT'))->withInput(['id' => ['not', 'an', 'id']]);

        $this->expectException(OperationResolutionException::class);
        $this->expectExceptionMessageMatches('/string or integer/');

        $this->resolver->resolve($request, $this->resource);
    }

    public function test_float_raw_id_throws_operation_resolution_exception(): void
    {
        $request = (new FakeCrudRequest('PUT'))->withInput(['id' => 3.14]);

        $this->expectException(OperationResolutionException::class);

        $this->resolver->resolve($request, $this->resource);
    }

    public function test_bool_raw_id_throws_operation_resolution_exception(): void
    {
        $request = (new FakeCrudRequest('PUT'))->withInput(['id' => true]);

        $this->expectException(OperationResolutionException::class);

        $this->resolver->resolve($request, $this->resource);
    }

    public function test_int_raw_id_does_not_throw(): void
    {
        $request = (new FakeCrudRequest('PUT'))->withInput(['id' => 42]);

        $resolved = $this->resolver->resolve($request, $this->resource);

        self::assertNotNull($resolved->resourceId);
        self::assertSame(42, $resolved->resourceId->value);
    }

    public function test_string_raw_id_does_not_throw(): void
    {
        $request = (new FakeCrudRequest('PUT'))->withInput(['id' => 'uuid-abc']);

        $resolved = $this->resolver->resolve($request, $this->resource);

        self::assertNotNull($resolved->resourceId);
        self::assertSame('uuid-abc', $resolved->resourceId->value);
    }

    // ── Lines 224,225: parseHint type check + Throw_ ─────────────────────────

    public function test_array_client_hint_throws_operation_resolution_exception(): void
    {
        $request = (new FakeCrudRequest('DELETE'))->withInput(['_crud_operation' => ['delete']]);

        $this->expectException(OperationResolutionException::class);
        $this->expectExceptionMessageMatches('/string/');

        $this->resolver->resolve($request, $this->resource);
    }

    public function test_float_client_hint_throws(): void
    {
        $request = (new FakeCrudRequest('DELETE'))->withInput(['_crud_operation' => 1.5]);

        $this->expectException(OperationResolutionException::class);

        $this->resolver->resolve($request, $this->resource);
    }

    // ── Lines 228,232: CastString in fromString and error message ────────────

    public function test_integer_hint_resolves_if_matching_operation_value(): void
    {
        // OperationType enum values are strings like 'read', 'create', etc.
        // int hint '42' won't match any operation → "Unknown hint" exception
        $request = (new FakeCrudRequest('DELETE'))->withInput(['_crud_operation' => 42]);

        $this->expectException(OperationResolutionException::class);
        // 42 cast to string = '42', which is not a valid operation name
        $this->expectExceptionMessageMatches('/42/');

        $this->resolver->resolve($request, $this->resource);
    }

    // ── Line 249: UnwrapArrayValues in intersectWithMethod ───────────────────

    public function test_intersect_with_method_reindexes_compatible_operations(): void
    {
        // DELETE compat = [Delete(0), BulkDelete(1)]. If allowed=[BulkDelete],
        // array_filter preserves index 1. array_values reindexes to 0.
        // Without array_values, $compatible[0] would be null → TypeError.
        $resolved = $this->resolver->resolve(
            new FakeCrudRequest('DELETE'),
            $this->resource,
            null,
            RouteOperationConfig::allow(OperationType::BulkDelete),
        );

        self::assertSame(OperationType::BulkDelete, $resolved->operation);
    }

    // ── Lines 260,263: strtolower/CastString in headerValue ─────────────────

    public function test_hint_from_uppercase_header_is_accepted(): void
    {
        // UnwrapStrToLower: if strtolower($key) is removed, 'DATA-BAMISE-CRUD-OP' won't match
        $request = (new FakeCrudRequest('DELETE'))
            ->withHeaders(['DATA-BAMISE-CRUD-OP' => 'bulk_delete']);

        $resolved = $this->resolver->resolve($request, $this->resource);

        self::assertSame(OperationType::BulkDelete, $resolved->operation);
    }

    public function test_hint_from_mixed_case_header_is_accepted(): void
    {
        $request = (new FakeCrudRequest('DELETE'))
            ->withHeaders(['Data-Bamise-Crud-Op' => 'bulk_delete']);

        $resolved = $this->resolver->resolve($request, $this->resource);

        self::assertSame(OperationType::BulkDelete, $resolved->operation);
    }

    // ── Line 264: Continue_ in headerValue loop ───────────────────────────────

    public function test_unrelated_headers_are_skipped_in_hint_lookup(): void
    {
        $request = (new FakeCrudRequest('DELETE'))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => '*/*',
                'data-bamise-crud-op' => 'bulk_delete',
                'Authorization' => 'Bearer token',
            ]);

        $resolved = $this->resolver->resolve($request, $this->resource);

        self::assertSame(OperationType::BulkDelete, $resolved->operation);
    }

    // ── Line 268: IncrementInteger on $value[0] for array header ────────────

    public function test_array_header_hint_uses_first_element(): void
    {
        $request = (new FakeCrudRequest('DELETE'))
            ->withHeaders(['data-bamise-crud-op' => ['bulk_delete', 'ignored']]);

        $resolved = $this->resolver->resolve($request, $this->resource);

        self::assertSame(OperationType::BulkDelete, $resolved->operation);
    }

    // ── rawId from primaryKey field (not just 'id') ───────────────────────────

    public function test_raw_id_resolved_from_primary_key_field(): void
    {
        $resource = new Resource('orders', 'orders_table', 'order_id');
        $request = (new FakeCrudRequest('DELETE'))->withInput(['order_id' => 99]);

        $resolved = $this->resolver->resolve($request, $resource);

        self::assertNotNull($resolved->resourceId);
        self::assertSame(99, $resolved->resourceId->value);
    }
}
