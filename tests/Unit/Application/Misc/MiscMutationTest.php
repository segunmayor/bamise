<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Misc;

use Bamise\Application\DTO\ResponseEnvelope;
use Bamise\Application\Handler\CrudOrchestrator;
use Bamise\Application\Response\ExceptionMapper;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Exception\AuthorizationException;
use Bamise\Contract\Exception\BamiseException;
use Bamise\Contract\Exception\CsrfException;
use Bamise\Contract\Exception\OperationResolutionException;
use Bamise\Contract\Exception\RateLimitException;
use Bamise\Contract\Exception\ValidationException;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Domain\Event\LifecycleEventFactory;
use Bamise\Domain\Exception\InsufficientPermissionException;
use Bamise\Domain\Exception\MassAssignmentException;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakeEventDispatcherPort;
use PHPUnit\Framework\TestCase;

/**
 * Misc mutation-killing tests:
 * - ResponseEnvelope: httpStatus default pin
 * - CrudOrchestrator: match arm coverage for all operations
 * - ExceptionMapper: httpStatus for each exception type
 */
final class MiscMutationTest extends TestCase
{
    // ── ResponseEnvelope: httpStatus default = 200 ───────────────────────────

    public function test_response_envelope_default_http_status_is_200(): void
    {
        $envelope = new ResponseEnvelope(success: true);

        self::assertSame(200, $envelope->httpStatus);
    }

    public function test_response_envelope_custom_http_status(): void
    {
        $envelope = new ResponseEnvelope(success: false, httpStatus: 404);

        self::assertSame(404, $envelope->httpStatus);
    }

    // ── ExceptionMapper: exact httpStatus per exception ──────────────────────

    public function test_exception_mapper_authorization_exception_is_403(): void
    {
        $mapper = new ExceptionMapper();

        $envelope = $mapper->map(new AuthorizationException('denied'));

        self::assertSame(403, $envelope->httpStatus);
    }

    public function test_exception_mapper_csrf_exception_is_403(): void
    {
        $mapper = new ExceptionMapper();

        $envelope = $mapper->map(new CsrfException('csrf'));

        self::assertSame(403, $envelope->httpStatus);
    }

    public function test_exception_mapper_insufficient_permission_is_403(): void
    {
        $mapper = new ExceptionMapper();

        $envelope = $mapper->map(new InsufficientPermissionException('denied'));

        self::assertSame(403, $envelope->httpStatus);
    }

    public function test_exception_mapper_rate_limit_exception_is_429(): void
    {
        $mapper = new ExceptionMapper();

        $envelope = $mapper->map(new RateLimitException('rate'));

        self::assertSame(429, $envelope->httpStatus);
    }

    public function test_exception_mapper_validation_exception_is_422(): void
    {
        $mapper = new ExceptionMapper();

        $envelope = $mapper->map(new ValidationException('invalid'));

        self::assertSame(422, $envelope->httpStatus);
    }

    public function test_exception_mapper_mass_assignment_exception_is_422(): void
    {
        $mapper = new ExceptionMapper();

        $envelope = $mapper->map(new MassAssignmentException('mass'));

        self::assertSame(422, $envelope->httpStatus);
    }

    public function test_exception_mapper_operation_resolution_exception_is_400(): void
    {
        $mapper = new ExceptionMapper();

        $envelope = $mapper->map(new OperationResolutionException('bad op'));

        self::assertSame(400, $envelope->httpStatus);
    }

    public function test_exception_mapper_bamise_exception_is_400(): void
    {
        $mapper = new ExceptionMapper();
        $ex = new BamiseException('bamise');

        $envelope = $mapper->map($ex);

        self::assertSame(400, $envelope->httpStatus);
    }

    public function test_exception_mapper_generic_exception_is_500(): void
    {
        $mapper = new ExceptionMapper();

        $envelope = $mapper->map(new \RuntimeException('generic'));

        self::assertSame(500, $envelope->httpStatus);
    }

    public function test_exception_mapper_includes_message_in_errors(): void
    {
        $mapper = new ExceptionMapper();

        $envelope = $mapper->map(new \RuntimeException('boom'));

        self::assertSame('boom', $envelope->errors['message']);
    }

    // ── CrudOrchestrator: hasLifecycleEvents match arm coverage ──────────────

    private function makeOrchestrator(): array
    {
        $dispatcher = new FakeEventDispatcherPort();
        $innerResult = new CrudResult(success: true, data: ['id' => 1]);
        $inner = new class ($innerResult) implements CrudHandlerInterface {
            public function __construct(private CrudResult $r) {}
            public function handle(CrudContext $c): CrudResult { return $this->r; }
        };
        $orchestrator = new CrudOrchestrator(
            $dispatcher,
            new LifecycleEventFactory(),
            $inner,
        );

        return [$orchestrator, $dispatcher];
    }

    private function context(OperationType $op): CrudContext
    {
        return new CrudContext($op, 'products', [], null, new FakeCrudRequest());
    }

    public function test_create_dispatches_lifecycle_events(): void
    {
        [$orch, $dispatcher] = $this->makeOrchestrator();

        $orch->handle($this->context(OperationType::Create));

        self::assertGreaterThan(0, count($dispatcher->dispatched));
    }

    public function test_update_dispatches_lifecycle_events(): void
    {
        [$orch, $dispatcher] = $this->makeOrchestrator();

        $orch->handle($this->context(OperationType::Update));

        self::assertGreaterThan(0, count($dispatcher->dispatched));
    }

    public function test_delete_dispatches_lifecycle_events(): void
    {
        [$orch, $dispatcher] = $this->makeOrchestrator();

        $orch->handle($this->context(OperationType::Delete));

        self::assertGreaterThan(0, count($dispatcher->dispatched));
    }

    public function test_bulk_update_dispatches_lifecycle_events(): void
    {
        [$orch, $dispatcher] = $this->makeOrchestrator();

        $orch->handle($this->context(OperationType::BulkUpdate));

        self::assertGreaterThan(0, count($dispatcher->dispatched));
    }

    public function test_bulk_delete_dispatches_lifecycle_events(): void
    {
        [$orch, $dispatcher] = $this->makeOrchestrator();

        $orch->handle($this->context(OperationType::BulkDelete));

        self::assertGreaterThan(0, count($dispatcher->dispatched));
    }

    public function test_read_does_not_dispatch_lifecycle_events(): void
    {
        [$orch, $dispatcher] = $this->makeOrchestrator();

        $orch->handle($this->context(OperationType::Read));

        self::assertCount(0, $dispatcher->dispatched);
    }
}
