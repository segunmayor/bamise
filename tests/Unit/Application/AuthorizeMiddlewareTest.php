<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application;

use Bamise\Application\Middleware\AuthorizeMiddleware;
use Bamise\Application\Response\ExceptionMapper;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
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
    public function test_permission_denied_returns_failure_envelope(): void
    {
        $middleware = new AuthorizeMiddleware(
            new PermissionEvaluator(),
            new PolicyEvaluator(new FakePolicyPort(true), new OperationTypeMapper()),
        );
        $context = new CrudContext(
            OperationType::Delete,
            'users',
            [],
            new Subject(1, [], ['users.read']),
            new FakeCrudRequest(),
        );
        $next = new class implements CrudHandlerInterface {
            public function handle(CrudContext $context): CrudResult
            {
                return new CrudResult(success: true);
            }
        };

        try {
            $middleware->process($context, $next);
            self::fail('Expected InsufficientPermissionException');
        } catch (InsufficientPermissionException $exception) {
            $envelope = (new ExceptionMapper())->map($exception);
        }

        self::assertFalse($envelope->success);
        self::assertSame(403, $envelope->httpStatus);
        self::assertStringContainsString('users.delete', $envelope->errors['message']);
    }

    public function test_permission_granted_calls_next(): void
    {
        $middleware = new AuthorizeMiddleware(
            new PermissionEvaluator(),
            new PolicyEvaluator(new FakePolicyPort(true), new OperationTypeMapper()),
        );
        $context = new CrudContext(
            OperationType::Read,
            'users',
            [],
            new Subject(1, [], ['users.read']),
            new FakeCrudRequest(),
        );
        $next = new class implements CrudHandlerInterface {
            public function handle(CrudContext $context): CrudResult
            {
                return new CrudResult(success: true, data: ['passed' => true]);
            }
        };

        $result = $middleware->process($context, $next);

        self::assertTrue($result->success);
        self::assertSame(['passed' => true], $result->data);
    }
}
