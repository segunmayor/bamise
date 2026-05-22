<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application;

use Bamise\Application\Middleware\MiddlewarePipeline;
use Bamise\Application\Middleware\PrioritizedMiddleware;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Http\CrudRequestInterface;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class MiddlewarePipelineTest extends TestCase
{
    /** @var list<string> */
    public static array $executionOrder = [];

    protected function setUp(): void
    {
        self::$executionOrder = [];
    }

    public function test_executes_middleware_in_priority_order(): void
    {
        $context = $this->context();
        $terminal = new TerminalHandler();
        $pipeline = new MiddlewarePipeline(
            [
                new PrioritizedMiddleware(new RecordingMiddleware('second'), 200),
                new PrioritizedMiddleware(new RecordingMiddleware('first'), 100),
            ],
            $terminal,
        );

        $result = $pipeline->handle($context);

        self::assertTrue($result->success);
        self::assertSame(['first', 'second', 'terminal'], self::$executionOrder);
    }

    private function context(): CrudContext
    {
        $request = new FakeCrudRequest('GET', '/users');

        return new CrudContext(
            OperationType::Read,
            'users',
            [],
            null,
            $request,
        );
    }
}

final class RecordingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $name,
    ) {
    }

    public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
    {
        MiddlewarePipelineTest::$executionOrder[] = $this->name;

        return $next->handle($context);
    }
}

final class TerminalHandler implements CrudHandlerInterface
{
    public function handle(CrudContext $context): CrudResult
    {
        unset($context);
        MiddlewarePipelineTest::$executionOrder[] = 'terminal';

        return new CrudResult(success: true, data: ['ok' => true]);
    }
}
