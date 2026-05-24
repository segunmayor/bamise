<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Middleware;

use Bamise\Application\Middleware\AuthorizeMiddleware;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Exception\AuthorizationException;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Domain\Exception\InsufficientPermissionException;
use Bamise\Domain\Model\Subject;
use Bamise\Domain\Policy\PolicyEvaluator;
use Bamise\Domain\Service\OperationTypeMapper;
use Bamise\Domain\Service\PermissionEvaluator;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakePolicyPort;
use PHPUnit\Framework\TestCase;

final class AuthorizeMiddlewareTest extends TestCase
{
    private function middleware(bool $policyAllows = true): AuthorizeMiddleware
    {
        return new AuthorizeMiddleware(
            new PermissionEvaluator(),
            new PolicyEvaluator(new FakePolicyPort($policyAllows), new OperationTypeMapper()),
        );
    }

    private function terminal(): CrudHandlerInterface
    {
        return new class implements CrudHandlerInterface {
            public function handle(CrudContext $context): CrudResult
            {
                return new CrudResult(success: true, data: ['terminal' => true]);
            }
        };
    }

    private function context(OperationType $op = OperationType::Read, ?object $subject = null): CrudContext
    {
        return new CrudContext($op, 'users', [], $subject, new FakeCrudRequest('GET', '/users'));
    }

    // ── Unauthenticated (no Subject) ─────────────────────────────────────────

    public function test_throws_authorization_exception_when_subject_is_null(): void
    {
        $caught = null;
        try {
            $this->middleware()->process($this->context(subject: null), $this->terminal());
            self::fail('Expected AuthorizationException for null subject.');
        } catch (AuthorizationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertStringContainsString('Authentication required', $caught->getMessage());
    }

    public function test_throws_authorization_exception_when_subject_is_not_a_subject_instance(): void
    {
        $caught = null;
        try {
            $this->middleware()->process($this->context(subject: new \stdClass()), $this->terminal());
            self::fail('Expected AuthorizationException for non-Subject object.');
        } catch (AuthorizationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
    }

    // ── Insufficient permission ───────────────────────────────────────────────

    public function test_throws_insufficient_permission_when_subject_has_no_permissions(): void
    {
        $subject = new Subject(1, [], []);

        $this->expectException(InsufficientPermissionException::class);
        $this->middleware()->process($this->context(OperationType::Read, $subject), $this->terminal());
    }

    public function test_throws_insufficient_permission_for_wrong_operation(): void
    {
        // Has read, not create
        $subject = new Subject(1, [], ['users.read']);

        $this->expectException(InsufficientPermissionException::class);
        $this->middleware()->process($this->context(OperationType::Create, $subject), $this->terminal());
    }

    public function test_throws_insufficient_permission_for_wrong_resource(): void
    {
        // Has posts.read, not users.read
        $subject = new Subject(1, [], ['posts.read']);
        $context = new CrudContext(OperationType::Read, 'users', [], $subject, new FakeCrudRequest());

        $this->expectException(InsufficientPermissionException::class);
        $this->middleware()->process($context, $this->terminal());
    }

    // ── Policy denial ─────────────────────────────────────────────────────────

    public function test_throws_authorization_exception_when_policy_denies(): void
    {
        $subject = new Subject(1, [], ['users.read']);
        $middleware = $this->middleware(policyAllows: false);

        $this->expectException(AuthorizationException::class);
        $middleware->process($this->context(OperationType::Read, $subject), $this->terminal());
    }

    public function test_policy_denial_message_contains_resource_name(): void
    {
        $subject = new Subject(1, [], ['users.read']);
        $middleware = $this->middleware(policyAllows: false);

        $caught = null;
        try {
            $middleware->process($this->context(OperationType::Read, $subject), $this->terminal());
        } catch (AuthorizationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertStringContainsString('users', $caught->getMessage());
    }

    // ── Happy path ───────────────────────────────────────────────────────────

    public function test_calls_next_when_subject_has_read_permission_and_policy_allows(): void
    {
        $subject = new Subject(1, [], ['users.read']);
        $result  = $this->middleware()->process($this->context(OperationType::Read, $subject), $this->terminal());

        self::assertTrue($result->success);
    }

    public function test_calls_next_for_create_with_create_permission(): void
    {
        $subject = new Subject(1, [], ['users.create']);
        $result  = $this->middleware()->process($this->context(OperationType::Create, $subject), $this->terminal());

        self::assertTrue($result->success);
    }

    public function test_calls_next_for_delete_with_delete_permission(): void
    {
        $subject = new Subject(1, [], ['users.delete']);
        $result  = $this->middleware()->process($this->context(OperationType::Delete, $subject), $this->terminal());

        self::assertTrue($result->success);
    }

    public function test_subject_with_multiple_permissions_uses_correct_one(): void
    {
        $subject = new Subject(1, [], ['posts.create', 'users.read', 'orders.delete']);
        $result  = $this->middleware()->process($this->context(OperationType::Read, $subject), $this->terminal());

        self::assertTrue($result->success);
    }

    public function test_high_privilege_subject_passes_all_operations(): void
    {
        $perms = ['users.read', 'users.create', 'users.update', 'users.delete'];
        $subject = new Subject(99, ['admin'], $perms);

        foreach ([OperationType::Read, OperationType::Create, OperationType::Update, OperationType::Delete] as $op) {
            $result = $this->middleware()->process($this->context($op, $subject), $this->terminal());
            self::assertTrue($result->success, "Operation $op->value must succeed for admin subject.");
        }
    }
}
