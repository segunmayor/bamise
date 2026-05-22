<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application;

use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Context\SubjectFactory;
use Bamise\Application\CrudApplication;
use Bamise\Application\Handler\CrudOrchestrator;
use Bamise\Application\Handler\StrategyDispatchHandler;
use Bamise\Application\Middleware\AuditMiddleware;
use Bamise\Application\Middleware\AuthenticationMiddleware;
use Bamise\Application\Middleware\AuthorizeMiddleware;
use Bamise\Application\Middleware\CsrfMiddleware;
use Bamise\Application\Middleware\MiddlewarePipeline;
use Bamise\Application\Middleware\PrioritizedMiddleware;
use Bamise\Application\Middleware\RateLimitMiddleware;
use Bamise\Application\Middleware\SanitizeMiddleware;
use Bamise\Application\Middleware\ValidateMiddleware;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Response\ExceptionMapper;
use Bamise\Application\Response\ResponseMapper;
use Bamise\Application\Strategy\CreateStrategy;
use Bamise\Application\Strategy\DeleteStrategy;
use Bamise\Application\Strategy\OperationStrategyFactory;
use Bamise\Application\Strategy\ReadStrategy;
use Bamise\Application\Strategy\UpdateStrategy;
use Bamise\Domain\Event\LifecycleEventFactory;
use Bamise\Domain\Model\Subject;
use Bamise\Domain\Policy\PolicyEvaluator;
use Bamise\Domain\Service\FillableGuard;
use Bamise\Domain\Service\OperationResolver;
use Bamise\Domain\Service\OperationTypeMapper;
use Bamise\Domain\Service\PermissionEvaluator;
use Bamise\Tests\Fixtures\FakeAuditLoggerPort;
use Bamise\Tests\Fixtures\FakeAuthPort;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakeCsrfPort;
use Bamise\Tests\Fixtures\FakeEventDispatcherPort;
use Bamise\Tests\Fixtures\FakePolicyPort;
use Bamise\Tests\Fixtures\FakeRateLimiterPort;
use Bamise\Tests\Fixtures\FakeRepository;
use Bamise\Tests\Fixtures\FakeResourceDefinition;
use Bamise\Tests\Fixtures\FakeSanitizerPort;
use Bamise\Tests\Fixtures\FakeValidatorPort;
use PHPUnit\Framework\TestCase;

final class CrudApplicationTest extends TestCase
{
    public function test_happy_path_read_returns_not_implemented_strategy_result(): void
    {
        $registry = new ResourceRegistry(['users' => new FakeResourceDefinition()]);
        $subject = new Subject(1, [], ['users.read']);
        $contextFactory = new CrudContextFactory();
        $repository = new FakeRepository();
        $strategyFactory = new OperationStrategyFactory(
            new CreateStrategy($repository),
            new ReadStrategy($repository),
            new UpdateStrategy($repository),
            new DeleteStrategy($repository),
        );
        $terminal = new CrudOrchestrator(
            new FakeEventDispatcherPort(),
            new LifecycleEventFactory(),
            new StrategyDispatchHandler($strategyFactory),
        );
        $pipeline = new MiddlewarePipeline(
            [
                new PrioritizedMiddleware(new RateLimitMiddleware(new FakeRateLimiterPort()), 100),
                new PrioritizedMiddleware(
                    new AuthenticationMiddleware(
                        new FakeAuthPort($subject),
                        new SubjectFactory(),
                        $contextFactory,
                    ),
                    200,
                ),
                new PrioritizedMiddleware(new CsrfMiddleware(new FakeCsrfPort()), 300),
                new PrioritizedMiddleware(
                    new SanitizeMiddleware(new FakeSanitizerPort(), $contextFactory),
                    400,
                ),
                new PrioritizedMiddleware(
                    new ValidateMiddleware(
                        new FakeValidatorPort(),
                        $registry,
                        new FillableGuard(),
                        $contextFactory,
                    ),
                    500,
                ),
                new PrioritizedMiddleware(
                    new AuthorizeMiddleware(
                        new PermissionEvaluator(),
                        new PolicyEvaluator(new FakePolicyPort(true), new OperationTypeMapper()),
                    ),
                    600,
                ),
                new PrioritizedMiddleware(new AuditMiddleware(new FakeAuditLoggerPort()), 900),
            ],
            $terminal,
        );
        $app = new CrudApplication(
            $registry,
            $contextFactory,
            new OperationResolver(new OperationTypeMapper()),
            $pipeline,
            new ResponseMapper(),
            new ExceptionMapper(),
        );

        $envelope = $app->handle(
            new FakeCrudRequest('GET', '/users'),
            'users',
        );

        self::assertFalse($envelope->success);
        self::assertSame(422, $envelope->httpStatus);
        self::assertSame('Infrastructure not wired', $envelope->errors['message']);
    }
}
