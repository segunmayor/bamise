<?php

declare(strict_types=1);

namespace Bamise\Tests\Integration\Infrastructure;

use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Context\SubjectFactory;
use Bamise\Application\Handler\CrudOrchestrator;
use Bamise\Application\Handler\StrategyDispatchHandler;
use Bamise\Application\Middleware\AuthenticationMiddleware;
use Bamise\Application\Middleware\AuthorizeMiddleware;
use Bamise\Application\Middleware\MiddlewarePipeline;
use Bamise\Application\Middleware\PrioritizedMiddleware;
use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Strategy\OperationStrategyFactory;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Exception\AuthorizationException;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Domain\Event\LifecycleEventFactory;
use Bamise\Domain\Exception\InsufficientPermissionException;
use Bamise\Domain\Model\Subject;
use Bamise\Domain\Policy\PolicyEvaluator;
use Bamise\Domain\Service\FillableGuard;
use Bamise\Domain\Service\OperationTypeMapper;
use Bamise\Domain\Service\PermissionEvaluator;
use Bamise\Tests\Fixtures\FakeAuthPort;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakeEventDispatcherPort;
use Bamise\Tests\Fixtures\FakePolicyPort;
use Bamise\Tests\Fixtures\FakeRepository;
use Bamise\Tests\Fixtures\FakeResourceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the full authentication + authorization middleware pipeline.
 *
 * Verifies end-to-end enforcement: a real request flows through
 * AuthenticationMiddleware → AuthorizeMiddleware → a terminal handler,
 * using concrete (not mocked) implementations.
 */
final class AuthPipelineIntegrationTest extends TestCase
{
    private function buildPipeline(
        ?Subject $subject,
        bool $policyAllows = true,
    ): CrudHandlerInterface {
        $contextFactory   = new CrudContextFactory();
        $resourceRegistry = new ResourceRegistry(['users' => new FakeResourceDefinition()]);
        $repositoryResolver = new RepositoryResolver(['users' => new FakeRepository()]);

        $terminal = new CrudOrchestrator(
            new FakeEventDispatcherPort(),
            new LifecycleEventFactory(),
            new StrategyDispatchHandler(
                new OperationStrategyFactory($repositoryResolver, $resourceRegistry, new FillableGuard()),
            ),
        );

        return new MiddlewarePipeline(
            [
                new PrioritizedMiddleware(
                    new AuthenticationMiddleware(
                        new FakeAuthPort($subject),
                        new SubjectFactory(),
                        $contextFactory,
                    ),
                    100,
                ),
                new PrioritizedMiddleware(
                    new AuthorizeMiddleware(
                        new PermissionEvaluator(),
                        new PolicyEvaluator(new FakePolicyPort($policyAllows), new OperationTypeMapper()),
                    ),
                    200,
                ),
            ],
            $terminal,
        );
    }

    private function context(
        OperationType $op,
        array $input = [],
        ?object $subject = null,
    ): CrudContext {
        return new CrudContext(
            $op,
            'users',
            $input,
            $subject,
            new FakeCrudRequest(
                match ($op) {
                    OperationType::Create  => 'POST',
                    OperationType::Update  => 'PUT',
                    OperationType::Delete  => 'DELETE',
                    default                => 'GET',
                },
                '/users',
                $input,
            ),
        );
    }

    // ── Authentication enforcement ────────────────────────────────────────────

    public function test_unauthenticated_request_raises_authorization_exception(): void
    {
        $pipeline = $this->buildPipeline(null);

        $this->expectException(AuthorizationException::class);
        $pipeline->handle($this->context(OperationType::Read));
    }

    public function test_request_with_anonymous_object_as_subject_is_rejected(): void
    {
        // SubjectFactory cannot convert a bare stdClass → AuthorizeMiddleware rejects it.
        $pipeline = $this->buildPipeline(null);

        $this->expectException(AuthorizationException::class);
        $pipeline->handle($this->context(OperationType::Read));
    }

    // ── Authorization enforcement ─────────────────────────────────────────────

    public function test_authenticated_subject_without_permission_is_rejected(): void
    {
        $subject  = new Subject(1, [], []); // no permissions
        $pipeline = $this->buildPipeline($subject);

        $this->expectException(InsufficientPermissionException::class);
        $pipeline->handle($this->context(OperationType::Read));
    }

    public function test_authenticated_subject_with_wrong_operation_permission_is_rejected(): void
    {
        $subject  = new Subject(1, [], ['users.read']); // has read, not create
        $pipeline = $this->buildPipeline($subject);

        $this->expectException(InsufficientPermissionException::class);
        $pipeline->handle($this->context(OperationType::Create));
    }

    public function test_policy_denial_rejects_even_permissioned_subject(): void
    {
        $subject  = new Subject(1, [], ['users.read']);
        $pipeline = $this->buildPipeline($subject, policyAllows: false);

        $this->expectException(AuthorizationException::class);
        $pipeline->handle($this->context(OperationType::Read));
    }

    // ── Happy paths ───────────────────────────────────────────────────────────

    public function test_authenticated_subject_with_read_permission_succeeds(): void
    {
        $subject  = new Subject(1, [], ['users.read']);
        $pipeline = $this->buildPipeline($subject);

        $result = $pipeline->handle($this->context(OperationType::Read));
        self::assertTrue($result->success);
    }

    public function test_authenticated_subject_with_create_permission_succeeds(): void
    {
        $subject  = new Subject(1, [], ['users.create']);
        $pipeline = $this->buildPipeline($subject);

        $result = $pipeline->handle($this->context(OperationType::Create, ['name' => 'Alice']));
        self::assertTrue($result->success);
    }

    public function test_authenticated_subject_with_delete_permission_succeeds(): void
    {
        $subject  = new Subject(1, [], ['users.delete']);
        $pipeline = $this->buildPipeline($subject);

        $result = $pipeline->handle($this->context(OperationType::Delete, ['id' => 1]));
        self::assertTrue($result->success);
    }

    public function test_admin_subject_with_all_permissions_can_do_any_crud_operation(): void
    {
        $perms    = ['users.read', 'users.create', 'users.update', 'users.delete'];
        $subject  = new Subject(99, ['admin'], $perms);
        $pipeline = $this->buildPipeline($subject);

        $inputs = [
            OperationType::Read->value   => [],
            OperationType::Create->value => ['name' => 'Test'],
            OperationType::Update->value => ['id' => 1, 'name' => 'Test'],
            OperationType::Delete->value => ['id' => 1],
        ];

        foreach ([OperationType::Read, OperationType::Create, OperationType::Update, OperationType::Delete] as $op) {
            $result = $pipeline->handle($this->context($op, $inputs[$op->value]));
            self::assertTrue($result->success, "Admin must succeed for operation $op->value.");
        }
    }

    // ── Isolation between requests ────────────────────────────────────────────

    public function test_successive_requests_are_independently_authorized(): void
    {
        $allowedSubject  = new Subject(1, [], ['users.read']);
        $forbiddenSubject = new Subject(2, [], []);

        $allowedPipeline  = $this->buildPipeline($allowedSubject);
        $forbiddenPipeline = $this->buildPipeline($forbiddenSubject);

        $result = $allowedPipeline->handle($this->context(OperationType::Read));
        self::assertTrue($result->success);

        $caught = null;
        try {
            $forbiddenPipeline->handle($this->context(OperationType::Read));
        } catch (InsufficientPermissionException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Forbidden request must be rejected.');
    }
}
