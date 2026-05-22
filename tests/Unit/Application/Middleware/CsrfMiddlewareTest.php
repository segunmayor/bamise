<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Middleware;

use Bamise\Application\Middleware\CsrfMiddleware;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Exception\CsrfException;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakeCsrfPort;
use PHPUnit\Framework\TestCase;

final class CsrfMiddlewareTest extends TestCase
{
    private CrudHandlerInterface $next;

    protected function setUp(): void
    {
        $this->next = new class implements CrudHandlerInterface {
            public function handle(CrudContext $context): CrudResult
            {
                unset($context);

                return new CrudResult(success: true, data: ['passed' => true]);
            }
        };
    }

    public function test_read_operation_skips_csrf_check(): void
    {
        $middleware = new CsrfMiddleware(new FakeCsrfPort(valid: false));
        $context = new CrudContext(OperationType::Read, 'users', [], null, new FakeCrudRequest());

        $result = $middleware->process($context, $this->next);

        self::assertTrue($result->success);
    }

    public function test_create_with_valid_token_proceeds(): void
    {
        $middleware = new CsrfMiddleware(new FakeCsrfPort(valid: true));
        $context = new CrudContext(OperationType::Create, 'users', [], null, new FakeCrudRequest('POST', '/users'));

        $result = $middleware->process($context, $this->next);

        self::assertTrue($result->success);
    }

    public function test_create_with_invalid_token_throws_csrf_exception(): void
    {
        $middleware = new CsrfMiddleware(new FakeCsrfPort(valid: false));
        $context = new CrudContext(OperationType::Create, 'users', [], null, new FakeCrudRequest('POST', '/users'));

        $this->expectException(CsrfException::class);
        $middleware->process($context, $this->next);
    }

    /**
     * @return array<string, array{0: OperationType}>
     */
    public static function mutatingOperationsProvider(): array
    {
        return [
            'create'     => [OperationType::Create],
            'update'     => [OperationType::Update],
            'delete'     => [OperationType::Delete],
            'bulkUpdate' => [OperationType::BulkUpdate],
            'bulkDelete' => [OperationType::BulkDelete],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('mutatingOperationsProvider')]
    public function test_all_mutating_operations_require_valid_csrf(OperationType $operation): void
    {
        $middleware = new CsrfMiddleware(new FakeCsrfPort(valid: false));
        $context = new CrudContext($operation, 'users', [], null, new FakeCrudRequest());

        $this->expectException(CsrfException::class);
        $middleware->process($context, $this->next);
    }
}
