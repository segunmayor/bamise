<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Domain;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Exception\OperationResolutionException;
use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Contract\ValueObject\RouteOperationConfig;
use Bamise\Domain\Model\Resource;
use Bamise\Domain\Service\OperationResolver;
use Bamise\Domain\Service\OperationTypeMapper;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OperationResolverTest extends TestCase
{
    private OperationResolver $resolver;

    private Resource $resource;

    protected function setUp(): void
    {
        $this->resolver = new OperationResolver(new OperationTypeMapper());
        $this->resource = new Resource('users', 'users_table', 'id');
    }

    // =========================================================================
    // Priority 1 — RouteOperationConfig::pin() is the absolute authority
    // =========================================================================

    public function test_pinned_route_config_overrides_http_method(): void
    {
        $resolved = $this->resolver->resolve(
            new FakeCrudRequest('DELETE'),
            $this->resource,
            null,
            RouteOperationConfig::pin(OperationType::Read),
        );

        self::assertSame(OperationType::Read, $resolved->operation);
    }

    public function test_pinned_route_config_ignores_client_hint(): void
    {
        $resolved = $this->resolver->resolve(
            new FakeCrudRequest('POST', '/', ['_crud_operation' => 'delete']),
            $this->resource,
            null,
            RouteOperationConfig::pin(OperationType::Create),
        );

        self::assertSame(OperationType::Create, $resolved->operation);
    }

    public function test_pinned_route_config_ignores_header_hint(): void
    {
        $request = (new FakeCrudRequest('GET'))
            ->withHeaders(['data-bamise-crud-op' => 'delete']);

        $resolved = $this->resolver->resolve(
            $request,
            $this->resource,
            null,
            RouteOperationConfig::pin(OperationType::Read),
        );

        self::assertSame(OperationType::Read, $resolved->operation);
    }

    // =========================================================================
    // Priority 2 — HTTP method is the authoritative server signal
    // =========================================================================

    #[DataProvider('httpMethodProvider')]
    public function test_http_method_maps_to_operation(string $method, OperationType $expected): void
    {
        $resolved = $this->resolver->resolve(new FakeCrudRequest($method), $this->resource);

        self::assertSame($expected, $resolved->operation);
    }

    /**
     * @return iterable<string, array{string, OperationType}>
     */
    public static function httpMethodProvider(): iterable
    {
        yield 'GET maps to read'    => ['GET',    OperationType::Read];
        yield 'POST maps to create' => ['POST',   OperationType::Create];
        yield 'PUT maps to update'  => ['PUT',    OperationType::Update];
        yield 'PATCH maps to update' => ['PATCH',  OperationType::Update];
        yield 'DELETE maps to delete' => ['DELETE', OperationType::Delete];
    }

    public function test_unmappable_method_without_default_throws(): void
    {
        $this->expectException(OperationResolutionException::class);
        $this->resolver->resolve(new FakeCrudRequest('OPTIONS'), $this->resource);
    }

    public function test_default_operation_used_for_unknown_method(): void
    {
        $resolved = $this->resolver->resolve(
            new FakeCrudRequest('OPTIONS'),
            $this->resource,
            OperationType::Read,
        );

        self::assertSame(OperationType::Read, $resolved->operation);
    }

    public function test_resource_id_resolved_for_update(): void
    {
        $resolved = $this->resolver->resolve(
            (new FakeCrudRequest('PATCH'))->withInput(['id' => 42]),
            $this->resource,
        );

        self::assertSame(OperationType::Update, $resolved->operation);
        self::assertNotNull($resolved->resourceId);
        self::assertSame(42, $resolved->resourceId->value);
    }

    // =========================================================================
    // Priority 3 — RouteOperationConfig::allow() narrows the candidate
    // =========================================================================

    public function test_allowed_set_selects_bulk_delete_over_default_delete(): void
    {
        // DELETE maps to Delete by default, but the route only permits BulkDelete.
        $resolved = $this->resolver->resolve(
            new FakeCrudRequest('DELETE'),
            $this->resource,
            null,
            RouteOperationConfig::allow(OperationType::BulkDelete),
        );

        self::assertSame(OperationType::BulkDelete, $resolved->operation);
    }

    public function test_allowed_set_selects_bulk_update_over_default_update(): void
    {
        $resolved = $this->resolver->resolve(
            new FakeCrudRequest('PATCH'),
            $this->resource,
            null,
            RouteOperationConfig::allow(OperationType::BulkUpdate),
        );

        self::assertSame(OperationType::BulkUpdate, $resolved->operation);
    }

    public function test_allowed_set_preserves_compatible_http_candidate(): void
    {
        // DELETE maps to Delete; allowed set includes Delete → keep Delete.
        $resolved = $this->resolver->resolve(
            new FakeCrudRequest('DELETE'),
            $this->resource,
            null,
            RouteOperationConfig::allow(OperationType::Delete, OperationType::BulkDelete),
        );

        self::assertSame(OperationType::Delete, $resolved->operation);
    }

    public function test_allowed_set_incompatible_with_method_throws(): void
    {
        // Route only allows Create, but the method is GET (compatible only with Read).
        $this->expectException(OperationResolutionException::class);
        $this->expectExceptionMessageMatches('/compatible/i');

        $this->resolver->resolve(
            new FakeCrudRequest('GET'),
            $this->resource,
            null,
            RouteOperationConfig::allow(OperationType::Create),
        );
    }

    // =========================================================================
    // Priority 4 — Client hints: valid disambiguation within server-permitted set
    // =========================================================================

    public function test_client_hint_disambiguates_bulk_delete_on_delete_route(): void
    {
        $request = (new FakeCrudRequest('DELETE'))
            ->withInput(['_crud_operation' => 'bulk_delete']);

        $resolved = $this->resolver->resolve(
            $request,
            $this->resource,
            null,
            RouteOperationConfig::allow(OperationType::Delete, OperationType::BulkDelete),
        );

        self::assertSame(OperationType::BulkDelete, $resolved->operation);
    }

    public function test_client_hint_disambiguates_bulk_update_on_patch_route(): void
    {
        $request = (new FakeCrudRequest('PATCH'))
            ->withInput(['_crud_operation' => 'bulk_update']);

        $resolved = $this->resolver->resolve(
            $request,
            $this->resource,
            null,
            RouteOperationConfig::allow(OperationType::Update, OperationType::BulkUpdate),
        );

        self::assertSame(OperationType::BulkUpdate, $resolved->operation);
    }

    public function test_valid_hint_accepted_on_open_config(): void
    {
        $request = (new FakeCrudRequest('DELETE'))
            ->withInput(['_crud_operation' => 'bulk_delete']);

        $resolved = $this->resolver->resolve(
            $request,
            $this->resource,
            null,
            RouteOperationConfig::open(),
        );

        self::assertSame(OperationType::BulkDelete, $resolved->operation);
    }

    // =========================================================================
    // SEC-01 — Forged operation values
    // =========================================================================

    public function test_get_request_with_forged_delete_hint_throws(): void
    {
        $request = new FakeCrudRequest('GET', '/', ['_crud_operation' => 'delete']);

        $this->expectException(OperationResolutionException::class);
        $this->expectExceptionMessageMatches('/compatible.*GET/i');

        $this->resolver->resolve($request, $this->resource);
    }

    public function test_get_request_with_forged_create_hint_throws(): void
    {
        $request = new FakeCrudRequest('GET', '/', ['_crud_operation' => 'create']);

        $this->expectException(OperationResolutionException::class);
        $this->resolver->resolve($request, $this->resource);
    }

    public function test_post_request_with_forged_delete_hint_throws(): void
    {
        $request = new FakeCrudRequest('POST', '/', ['_crud_operation' => 'delete']);

        $this->expectException(OperationResolutionException::class);
        $this->expectExceptionMessageMatches('/compatible.*POST/i');

        $this->resolver->resolve($request, $this->resource);
    }

    public function test_post_request_with_forged_bulk_delete_hint_throws(): void
    {
        $request = new FakeCrudRequest('POST', '/', ['_crud_operation' => 'bulk_delete']);

        $this->expectException(OperationResolutionException::class);
        $this->resolver->resolve($request, $this->resource);
    }

    public function test_delete_request_with_forged_create_hint_throws(): void
    {
        $request = new FakeCrudRequest('DELETE', '/', ['_crud_operation' => 'create']);

        $this->expectException(OperationResolutionException::class);
        $this->resolver->resolve($request, $this->resource);
    }

    public function test_patch_request_with_forged_read_hint_throws(): void
    {
        $request = new FakeCrudRequest('PATCH', '/', ['_crud_operation' => 'read']);

        $this->expectException(OperationResolutionException::class);
        $this->resolver->resolve($request, $this->resource);
    }

    public function test_header_hint_forging_across_method_boundary_throws(): void
    {
        $request = (new FakeCrudRequest('GET'))
            ->withHeaders(['data-bamise-crud-op' => 'delete']);

        $this->expectException(OperationResolutionException::class);
        $this->resolver->resolve($request, $this->resource);
    }

    public function test_unrecognised_hint_value_throws(): void
    {
        $request = new FakeCrudRequest('POST', '/', ['_crud_operation' => 'explode']);

        $this->expectException(OperationResolutionException::class);
        $this->expectExceptionMessageMatches('/Unknown.*explode/i');

        $this->resolver->resolve($request, $this->resource);
    }

    // =========================================================================
    // SEC-01 — Unauthorized operation switching
    // =========================================================================

    public function test_hint_cannot_switch_operation_outside_allowed_set(): void
    {
        // Route allows only BulkDelete; client tries to switch to Delete.
        $request = (new FakeCrudRequest('DELETE'))
            ->withInput(['_crud_operation' => 'delete']);

        $this->expectException(OperationResolutionException::class);
        $this->expectExceptionMessageMatches('/server-permitted/i');

        $this->resolver->resolve(
            $request,
            $this->resource,
            null,
            RouteOperationConfig::allow(OperationType::BulkDelete),
        );
    }

    public function test_hint_cannot_introduce_operation_not_in_allowed_set(): void
    {
        // Route allows Read; client tries to inject Create.
        $request = new FakeCrudRequest('GET', '/', ['_crud_operation' => 'read']);

        // read IS in the GET compat group and IS in the allowed set, so this succeeds.
        // Now try to forge it by switching to an incompatible type via header:
        $forgingRequest = (new FakeCrudRequest('GET'))
            ->withInput(['_crud_operation' => 'bulk_delete']);

        $this->expectException(OperationResolutionException::class);

        $this->resolver->resolve(
            $forgingRequest,
            $this->resource,
            null,
            RouteOperationConfig::allow(OperationType::Read),
        );
    }

    public function test_header_and_body_conflict_throws(): void
    {
        $request = (new FakeCrudRequest('DELETE'))
            ->withInput(['_crud_operation' => 'delete'])
            ->withHeaders(['data-bamise-crud-op' => 'bulk_delete']);

        $this->expectException(OperationResolutionException::class);
        $this->expectExceptionMessageMatches('/Ambiguous/i');

        $this->resolver->resolve($request, $this->resource);
    }

    // =========================================================================
    // SEC-01 — Privilege escalation attempts
    // =========================================================================

    public function test_read_only_route_rejects_mutating_hint(): void
    {
        $attempts = ['create', 'update', 'delete', 'bulk_update', 'bulk_delete'];

        foreach ($attempts as $attempt) {
            $request = new FakeCrudRequest('GET', '/', ['_crud_operation' => $attempt]);

            try {
                $this->resolver->resolve(
                    $request,
                    $this->resource,
                    null,
                    RouteOperationConfig::allow(OperationType::Read),
                );
                self::fail("Expected exception for hint '$attempt' on a read-only route.");
            } catch (OperationResolutionException) {
                // expected
            }
        }

        self::assertTrue(true); // all attempts correctly rejected
    }

    public function test_pinned_read_route_rejects_all_mutating_methods(): void
    {
        $mutatingMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        foreach ($mutatingMethods as $method) {
            $request = new FakeCrudRequest($method, '/', ['_crud_operation' => 'delete']);

            $resolved = $this->resolver->resolve(
                $request,
                $this->resource,
                null,
                RouteOperationConfig::pin(OperationType::Read),
            );

            self::assertSame(
                OperationType::Read,
                $resolved->operation,
                "Pin should enforce Read regardless of HTTP method $method.",
            );
        }
    }

    public function test_create_only_route_cannot_be_escalated_to_delete_via_hint(): void
    {
        $request = new FakeCrudRequest('POST', '/', ['_crud_operation' => 'delete']);

        $this->expectException(OperationResolutionException::class);

        $this->resolver->resolve(
            $request,
            $this->resource,
            null,
            RouteOperationConfig::allow(OperationType::Create),
        );
    }

    public function test_escalation_attempt_using_bulk_operation_on_single_record_route(): void
    {
        // Route is explicitly for single-record Delete only.
        $request = (new FakeCrudRequest('DELETE'))
            ->withInput(['_crud_operation' => 'bulk_delete']);

        $this->expectException(OperationResolutionException::class);
        $this->expectExceptionMessageMatches('/server-permitted/i');

        $this->resolver->resolve(
            $request,
            $this->resource,
            null,
            RouteOperationConfig::allow(OperationType::Delete),
        );
    }

    public function test_escalation_attempt_using_bulk_update_on_single_record_route(): void
    {
        $request = (new FakeCrudRequest('PATCH'))
            ->withInput(['_crud_operation' => 'bulk_update']);

        $this->expectException(OperationResolutionException::class);
        $this->expectExceptionMessageMatches('/server-permitted/i');

        $this->resolver->resolve(
            $request,
            $this->resource,
            null,
            RouteOperationConfig::allow(OperationType::Update),
        );
    }
}
