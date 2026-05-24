<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Middleware;

use Bamise\Application\Middleware\AuditMiddleware;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Domain\Model\Subject;
use Bamise\Tests\Fixtures\FakeAuditLoggerPort;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class AuditMiddlewareTest extends TestCase
{
    public function test_logs_record_on_successful_mutating_operation(): void
    {
        $logger = new FakeAuditLoggerPort();
        $middleware = new AuditMiddleware($logger);
        $context = $this->context(OperationType::Create);

        $middleware->process($context, $this->handler(success: true, data: ['id' => 1]));

        self::assertCount(1, $logger->records);
        self::assertSame('create', $logger->records[0]->action);
        self::assertSame('users', $logger->records[0]->resource);
    }

    public function test_does_not_log_on_failed_operation(): void
    {
        $logger = new FakeAuditLoggerPort();
        $middleware = new AuditMiddleware($logger);

        $middleware->process($this->context(OperationType::Create), $this->handler(success: false));

        self::assertCount(0, $logger->records);
    }

    public function test_does_not_log_on_read_operation(): void
    {
        $logger = new FakeAuditLoggerPort();
        $middleware = new AuditMiddleware($logger);

        $middleware->process($this->context(OperationType::Read), $this->handler(success: true));

        self::assertCount(0, $logger->records);
    }

    public function test_extracts_actor_from_subject(): void
    {
        $logger = new FakeAuditLoggerPort();
        $middleware = new AuditMiddleware($logger);
        $context = new CrudContext(
            OperationType::Delete,
            'users',
            ['id' => 3],
            new Subject(42, []),
            new FakeCrudRequest('DELETE', '/users'),
        );

        $middleware->process($context, $this->handler(success: true, data: ['id' => 3]));

        self::assertSame('42', $logger->records[0]->actor);
    }

    public function test_extracts_user_agent_from_headers(): void
    {
        $logger = new FakeAuditLoggerPort();
        $middleware = new AuditMiddleware($logger);
        $context = new CrudContext(
            OperationType::Update,
            'users',
            ['id' => 1, 'name' => 'Ada'],
            null,
            new FakeCrudRequest('PATCH', '/users', [], ['User-Agent' => 'TestBot/1.0']),
        );

        $middleware->process($context, $this->handler(success: true, data: ['id' => 1]));

        self::assertSame('TestBot/1.0', $logger->records[0]->userAgent);
    }

    private function context(OperationType $operation): CrudContext
    {
        return new CrudContext(
            $operation,
            'users',
            ['id' => 1],
            null,
            new FakeCrudRequest(),
        );
    }

    private function handler(bool $success, array $data = []): CrudHandlerInterface
    {
        return new class ($success, $data) implements CrudHandlerInterface {
            public function __construct(private bool $success, private array $data)
            {
            }
            public function handle(CrudContext $context): CrudResult
            {
                return new CrudResult(success: $this->success, data: $this->data);
            }
        };
    }
}
