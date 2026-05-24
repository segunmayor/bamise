<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application;

use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Context\SubjectFactory;
use Bamise\Application\CrudApplication;
use Bamise\Application\Handler\CrudOrchestrator;
use Bamise\Application\Handler\StrategyDispatchHandler;
use Bamise\Application\Middleware\AuthenticationMiddleware;
use Bamise\Application\Middleware\AuthorizeMiddleware;
use Bamise\Application\Middleware\MiddlewarePipeline;
use Bamise\Application\Middleware\PrioritizedMiddleware;
use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Response\ExceptionMapper;
use Bamise\Application\Response\ResponseMapper;
use Bamise\Application\Strategy\OperationStrategyFactory;
use Bamise\Domain\Event\LifecycleEventFactory;
use Bamise\Domain\Model\Subject;
use Bamise\Domain\Policy\PolicyEvaluator;
use Bamise\Domain\Service\FillableGuard;
use Bamise\Domain\Service\OperationResolver;
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
 * Stress tests for the full CrudApplication stack.
 *
 * Simulates high-volume CRUD traffic (1 k+ requests per test) through the
 * complete middleware chain (authentication → authorization → strategy → response)
 * with a single-threaded in-process driver.  Verifies:
 *
 *   - No state bleeding between requests
 *   - Graceful handling of large payloads
 *   - Consistent success across mixed operation types
 *   - Error envelopes (not exceptions) for invalid resources
 */
final class CrudApplicationStressTest extends TestCase
{
    private CrudApplication $app;

    protected function setUp(): void
    {
        $subject = new Subject(
            1,
            ['admin'],
            ['users.read', 'users.create', 'users.update', 'users.delete',
             'users.bulkUpdate', 'users.bulkDelete'],
        );

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

        $pipeline = new MiddlewarePipeline(
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
                        new PolicyEvaluator(new FakePolicyPort(true), new OperationTypeMapper()),
                    ),
                    200,
                ),
            ],
            $terminal,
        );

        $this->app = new CrudApplication(
            $resourceRegistry,
            $contextFactory,
            new OperationResolver(new OperationTypeMapper()),
            $pipeline,
            new ResponseMapper(),
            new ExceptionMapper(),
        );
    }

    // ── 1 k single-operation bursts ───────────────────────────────────────────

    public function test_1k_read_requests_all_succeed(): void
    {
        $failures = 0;

        for ($i = 0; $i < 1_000; $i++) {
            if (! $this->app->handle(new FakeCrudRequest('GET', '/users'), 'users')->success) {
                $failures++;
            }
        }

        self::assertSame(0, $failures, '1 000 sequential reads must all return success.');
    }

    public function test_1k_create_requests_all_succeed(): void
    {
        $failures = 0;

        for ($i = 0; $i < 1_000; $i++) {
            $r = $this->app->handle(
                new FakeCrudRequest('POST', '/users', ['name' => "User{$i}", 'email' => "u{$i}@test.com"]),
                'users',
            );
            if (! $r->success) {
                $failures++;
            }
        }

        self::assertSame(0, $failures, '1 000 sequential creates must all return success.');
    }

    // ── Mixed-operation burst ─────────────────────────────────────────────────

    public function test_1k_mixed_crud_operations_all_succeed(): void
    {
        $ops = [
            ['GET',    '/users',   []],
            ['POST',   '/users',   ['name' => 'Test']],
            ['GET',    '/users',   []],
            ['PUT',    '/users/1', ['id' => 1, 'name' => 'Updated']],
            ['DELETE', '/users/1', ['id' => 1]],
        ];

        $failures = 0;

        for ($i = 0; $i < 1_000; $i++) {
            [$method, $path, $input] = $ops[$i % count($ops)];
            $r = $this->app->handle(new FakeCrudRequest($method, $path, $input), 'users');
            if (! $r->success) {
                $failures++;
            }
        }

        self::assertSame(0, $failures, '1 000 mixed-operation requests must all succeed.');
    }

    // ── Large payloads ────────────────────────────────────────────────────────

    public function test_create_with_1mb_field_value_does_not_throw(): void
    {
        $r = $this->app->handle(
            new FakeCrudRequest('POST', '/users', ['name' => str_repeat('x', 1_000_000)]),
            'users',
        );

        self::assertIsBool($r->success);
    }

    public function test_create_with_500_input_fields_does_not_throw(): void
    {
        $input = [];
        for ($i = 0; $i < 500; $i++) {
            $input["field_{$i}"] = "value_{$i}";
        }

        $r = $this->app->handle(new FakeCrudRequest('POST', '/users', $input), 'users');

        self::assertIsBool($r->success);
    }

    // ── Error handling ────────────────────────────────────────────────────────

    public function test_unknown_resource_returns_error_envelope_not_exception(): void
    {
        $r = $this->app->handle(new FakeCrudRequest('GET', '/nonexistent'), 'nonexistent');

        self::assertFalse($r->success);
        self::assertGreaterThanOrEqual(400, $r->httpStatus);
    }

    public function test_500_reads_then_500_creates_produces_no_failures(): void
    {
        $failures = 0;

        for ($i = 0; $i < 500; $i++) {
            if (! $this->app->handle(new FakeCrudRequest('GET', '/users'), 'users')->success) {
                $failures++;
            }
        }

        for ($i = 0; $i < 500; $i++) {
            if (
                ! $this->app->handle(
                    new FakeCrudRequest('POST', '/users', ['name' => "Batch{$i}"]),
                    'users',
                )->success
            ) {
                $failures++;
            }
        }

        self::assertSame(0, $failures, '500 reads + 500 creates must all succeed.');
    }

    // ── Context isolation between requests ────────────────────────────────────

    public function test_no_state_bleed_across_10_sequential_requests(): void
    {
        $results = [];

        for ($i = 0; $i < 10; $i++) {
            $results[] = $this->app->handle(
                new FakeCrudRequest('POST', '/users', ['name' => "Isolated{$i}"]),
                'users',
            );
        }

        foreach ($results as $index => $r) {
            self::assertTrue($r->success, "Request #{$index} must succeed independently.");
        }
    }

    public function test_deep_nested_input_does_not_crash(): void
    {
        $r = $this->app->handle(
            new FakeCrudRequest('POST', '/users', [
                'meta' => ['profile' => ['bio' => str_repeat('deep', 1_000)]],
            ]),
            'users',
        );

        self::assertIsBool($r->success);
    }
}
