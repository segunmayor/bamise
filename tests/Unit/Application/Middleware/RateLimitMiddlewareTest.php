<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Middleware;

use Bamise\Application\Middleware\RateLimitMiddleware;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Exception\RateLimitException;
use Bamise\Contract\Security\RateLimiterPortInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakeRateLimiterPort;
use PHPUnit\Framework\TestCase;

final class RateLimitMiddlewareTest extends TestCase
{
    public function test_passes_through_when_limit_not_exceeded(): void
    {
        $middleware = new RateLimitMiddleware(new FakeRateLimiterPort(allowed: true));

        $result = $middleware->process($this->context(), $this->okHandler());

        self::assertTrue($result->success);
    }

    public function test_throws_when_rate_limit_exceeded(): void
    {
        $middleware = new RateLimitMiddleware(new FakeRateLimiterPort(allowed: false));

        $this->expectException(RateLimitException::class);

        $middleware->process($this->context(), $this->okHandler());
    }

    public function test_uses_client_ip_as_key_when_available(): void
    {
        $captured = null;
        $limiter = new class ($captured) implements RateLimiterPortInterface {
            public function __construct(private mixed &$captured)
            {
            }
            public function attempt(string $key): bool
            {
                $this->captured = $key;
                return true;
            }
            public function remaining(string $key): int
            {
                return 10;
            }
        };

        $context = new CrudContext(
            OperationType::Create,
            'users',
            [],
            null,
            new FakeCrudRequest('POST', '/users', [], [], '127.0.0.1'),
        );

        (new RateLimitMiddleware($limiter))->process($context, $this->okHandler());

        self::assertSame('127.0.0.1', $captured);
    }

    private function context(): CrudContext
    {
        return new CrudContext(
            OperationType::Create,
            'users',
            [],
            null,
            new FakeCrudRequest(),
        );
    }

    private function okHandler(): CrudHandlerInterface
    {
        return new class implements CrudHandlerInterface {
            public function handle(CrudContext $context): CrudResult
            {
                return new CrudResult(success: true);
            }
        };
    }
}
