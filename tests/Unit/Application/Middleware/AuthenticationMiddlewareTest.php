<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Middleware;

use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Context\SubjectFactory;
use Bamise\Application\Middleware\AuthenticationMiddleware;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Domain\Model\Subject;
use Bamise\Tests\Fixtures\FakeAuthPort;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class AuthenticationMiddlewareTest extends TestCase
{
    public function test_populates_subject_on_context_from_auth_port(): void
    {
        $subject = new Subject(99, ['admin']);
        $middleware = new AuthenticationMiddleware(
            new FakeAuthPort($subject),
            new SubjectFactory(),
            new CrudContextFactory(),
        );

        $capturedContext = null;
        $next = new class ($capturedContext) implements CrudHandlerInterface {
            public function __construct(private mixed &$capturedContext)
            {
            }
            public function handle(CrudContext $context): CrudResult
            {
                $this->capturedContext = $context;
                return new CrudResult(success: true);
            }
        };

        $context = new CrudContext(
            OperationType::Read,
            'users',
            [],
            null,
            new FakeCrudRequest(),
        );

        $middleware->process($context, $next);

        self::assertSame($subject, $capturedContext->subject);
    }

    public function test_passes_null_subject_when_auth_returns_null(): void
    {
        $middleware = new AuthenticationMiddleware(
            new FakeAuthPort(null),
            new SubjectFactory(),
            new CrudContextFactory(),
        );

        $capturedContext = null;
        $next = new class ($capturedContext) implements CrudHandlerInterface {
            public function __construct(private mixed &$capturedContext)
            {
            }
            public function handle(CrudContext $context): CrudResult
            {
                $this->capturedContext = $context;
                return new CrudResult(success: true);
            }
        };

        $middleware->process(
            new CrudContext(OperationType::Read, 'users', [], null, new FakeCrudRequest()),
            $next,
        );

        self::assertNull($capturedContext->subject);
    }
}
