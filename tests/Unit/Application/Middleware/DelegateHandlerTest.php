<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Middleware;

use Bamise\Application\Middleware\DelegateHandler;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class DelegateHandlerTest extends TestCase
{
    public function test_passes_context_to_middleware_process(): void
    {
        $context = new CrudContext(OperationType::Read, 'items', [], null, new FakeCrudRequest());
        $capturedContext = null;

        $next = new class implements CrudHandlerInterface {
            public function handle(CrudContext $context): CrudResult
            {
                unset($context);

                return new CrudResult(success: true);
            }
        };
        $middleware = new class ($capturedContext) implements MiddlewareInterface {
            public function __construct(private mixed &$captured)
            {
            }

            public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
            {
                $this->captured = $context;

                return $next->handle($context);
            }
        };

        $delegate = new DelegateHandler($middleware, $next);
        $result = $delegate->handle($context);

        self::assertTrue($result->success);
        self::assertSame($context, $capturedContext);
    }

    public function test_middleware_can_short_circuit_without_calling_next(): void
    {
        $context = new CrudContext(OperationType::Delete, 'items', [], null, new FakeCrudRequest());
        $nextCalled = false;

        $next = new class ($nextCalled) implements CrudHandlerInterface {
            public function __construct(private bool &$called)
            {
            }

            public function handle(CrudContext $context): CrudResult
            {
                $this->called = true;
                unset($context);

                return new CrudResult(success: true);
            }
        };
        $middleware = new class implements MiddlewareInterface {
            public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
            {
                unset($context, $next);

                return new CrudResult(success: false, data: ['blocked' => true]);
            }
        };

        $delegate = new DelegateHandler($middleware, $next);
        $result = $delegate->handle($context);

        self::assertFalse($result->success);
        self::assertFalse($nextCalled);
    }
}
