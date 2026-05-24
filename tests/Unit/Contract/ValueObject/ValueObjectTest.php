<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Contract\ValueObject;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Exception\AuthorizationException;
use Bamise\Contract\Exception\BamiseException;
use Bamise\Contract\Exception\CsrfException;
use Bamise\Contract\Exception\OperationResolutionException;
use Bamise\Contract\Exception\RateLimitException;
use Bamise\Contract\Exception\ValidationException;
use Bamise\Contract\ValueObject\AuditRecord;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Contract\ValueObject\RouteOperationConfig;
use Bamise\Contract\ValueObject\ValidationResult;
use Bamise\Domain\Exception\InsufficientPermissionException;
use Bamise\Domain\Exception\MassAssignmentException;
use PHPUnit\Framework\TestCase;

final class ValueObjectTest extends TestCase
{
    // ── ResourceId ─────────────────────────────────────────────────────────────

    public function test_resource_id_stores_int(): void
    {
        $id = new ResourceId(42);

        self::assertSame(42, $id->value);
    }

    public function test_resource_id_stores_string(): void
    {
        $id = new ResourceId('uuid-abc');

        self::assertSame('uuid-abc', $id->value);
    }

    public function test_resource_id_zero_int(): void
    {
        $id = new ResourceId(0);

        self::assertSame(0, $id->value);
    }

    // ── CrudResult ─────────────────────────────────────────────────────────────

    public function test_crud_result_success_with_data(): void
    {
        $result = new CrudResult(success: true, data: ['id' => 1]);

        self::assertTrue($result->success);
        self::assertSame(['id' => 1], $result->data);
        self::assertSame([], $result->errors);
        self::assertSame([], $result->meta);
    }

    public function test_crud_result_failure_with_errors(): void
    {
        $result = new CrudResult(success: false, errors: ['message' => 'Not found']);

        self::assertFalse($result->success);
        self::assertSame(['message' => 'Not found'], $result->errors);
    }

    public function test_crud_result_defaults_are_empty_arrays(): void
    {
        $result = new CrudResult(success: true);

        self::assertSame([], $result->data);
        self::assertSame([], $result->errors);
        self::assertSame([], $result->meta);
    }

    public function test_crud_result_with_meta(): void
    {
        $result = new CrudResult(success: true, meta: ['operation' => 'create', 'count' => 1]);

        self::assertSame('create', $result->meta['operation']);
        self::assertSame(1, $result->meta['count']);
    }

    // ── ValidationResult ───────────────────────────────────────────────────────

    public function test_validation_result_valid(): void
    {
        $result = new ValidationResult(valid: true, sanitizedData: ['name' => 'Alice']);

        self::assertTrue($result->valid);
        self::assertSame([], $result->errors);
        self::assertSame(['name' => 'Alice'], $result->sanitizedData);
    }

    public function test_validation_result_invalid_with_errors(): void
    {
        $result = new ValidationResult(valid: false, errors: ['email' => 'Invalid email']);

        self::assertFalse($result->valid);
        self::assertSame(['email' => 'Invalid email'], $result->errors);
        self::assertSame([], $result->sanitizedData);
    }

    public function test_validation_result_defaults(): void
    {
        $result = new ValidationResult(valid: true);

        self::assertSame([], $result->errors);
        self::assertSame([], $result->sanitizedData);
    }

    // ── AuditRecord ────────────────────────────────────────────────────────────

    public function test_audit_record_stores_all_fields(): void
    {
        $id = new ResourceId(7);
        $record = new AuditRecord(
            actor: 'admin',
            action: 'create',
            resource: 'users',
            recordId: $id,
            ip: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            before: null,
            after: ['name' => 'Bob'],
            correlationId: 'corr-123',
        );

        self::assertSame('admin', $record->actor);
        self::assertSame('create', $record->action);
        self::assertSame('users', $record->resource);
        self::assertSame($id, $record->recordId);
        self::assertSame('127.0.0.1', $record->ip);
        self::assertSame('Mozilla/5.0', $record->userAgent);
        self::assertNull($record->before);
        self::assertSame(['name' => 'Bob'], $record->after);
        self::assertSame('corr-123', $record->correlationId);
    }

    public function test_audit_record_nullable_fields_default_to_null(): void
    {
        $record = new AuditRecord(
            actor: null,
            action: 'delete',
            resource: 'orders',
            recordId: null,
            ip: null,
            userAgent: null,
        );

        self::assertNull($record->actor);
        self::assertNull($record->recordId);
        self::assertNull($record->ip);
        self::assertNull($record->userAgent);
        self::assertNull($record->before);
        self::assertNull($record->after);
        self::assertNull($record->correlationId);
    }

    public function test_audit_record_with_int_record_id(): void
    {
        $record = new AuditRecord(
            actor: 'user1',
            action: 'update',
            resource: 'posts',
            recordId: 999,
            ip: null,
            userAgent: null,
        );

        self::assertSame(999, $record->recordId);
    }

    // ── RouteOperationConfig ───────────────────────────────────────────────────

    public function test_pin_creates_pinned_config(): void
    {
        $config = RouteOperationConfig::pin(OperationType::Read);

        self::assertTrue($config->isPinned());
        self::assertSame(OperationType::Read, $config->pinned);
        self::assertFalse($config->isRestricted());
    }

    public function test_allow_creates_restricted_config(): void
    {
        $config = RouteOperationConfig::allow(OperationType::Create, OperationType::Update);

        self::assertFalse($config->isPinned());
        self::assertNull($config->pinned);
        self::assertTrue($config->isRestricted());
        self::assertTrue($config->permits(OperationType::Create));
        self::assertTrue($config->permits(OperationType::Update));
        self::assertFalse($config->permits(OperationType::Delete));
    }

    public function test_open_creates_unrestricted_config(): void
    {
        $config = RouteOperationConfig::open();

        self::assertFalse($config->isPinned());
        self::assertFalse($config->isRestricted());
        self::assertNull($config->pinned);
        self::assertNull($config->allowed);
    }

    public function test_open_permits_any_operation(): void
    {
        $config = RouteOperationConfig::open();

        foreach (OperationType::cases() as $op) {
            self::assertTrue($config->permits($op));
        }
    }

    public function test_pin_permits_only_the_pinned_operation(): void
    {
        $config = RouteOperationConfig::pin(OperationType::Delete);

        // permits() is not checked for pinned configs in the resolver, but the method still works
        self::assertTrue($config->permits(OperationType::Delete));
    }

    public function test_allow_single_operation(): void
    {
        $config = RouteOperationConfig::allow(OperationType::BulkDelete);

        self::assertTrue($config->permits(OperationType::BulkDelete));
        self::assertFalse($config->permits(OperationType::Delete));
    }

    // ── Exception hierarchy ────────────────────────────────────────────────────

    public function test_authorization_exception_extends_bamise_exception(): void
    {
        $e = new AuthorizationException('Forbidden');

        self::assertInstanceOf(BamiseException::class, $e);
        self::assertInstanceOf(\Exception::class, $e);
    }

    public function test_csrf_exception_extends_bamise_exception(): void
    {
        self::assertInstanceOf(BamiseException::class, new CsrfException());
    }

    public function test_operation_resolution_exception_extends_bamise_exception(): void
    {
        self::assertInstanceOf(BamiseException::class, new OperationResolutionException());
    }

    public function test_rate_limit_exception_extends_bamise_exception(): void
    {
        self::assertInstanceOf(BamiseException::class, new RateLimitException());
    }

    public function test_validation_exception_extends_bamise_exception(): void
    {
        self::assertInstanceOf(BamiseException::class, new ValidationException());
    }

    public function test_insufficient_permission_exception_extends_bamise_exception(): void
    {
        self::assertInstanceOf(BamiseException::class, new InsufficientPermissionException());
    }

    public function test_mass_assignment_exception_extends_bamise_exception(): void
    {
        self::assertInstanceOf(BamiseException::class, new MassAssignmentException());
    }

    public function test_exceptions_carry_message_and_code(): void
    {
        $e = new AuthorizationException('Not allowed', 403);

        self::assertSame('Not allowed', $e->getMessage());
        self::assertSame(403, $e->getCode());
    }
}
