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

/**
 * Targeted mutation-killing tests for RateLimitMiddleware.
 *
 * Kills escaped mutants:
 * - Line 25: Concat/ConcatOperandRemoval on key construction (resourceName:operation.value)
 */
final class RateLimitAndAuthMutationTest extends TestCase
{
    private function context(
        OperationType $op = OperationType::Read,
        string $resourceName = 'products',
        ?string $clientIp = null,
    ): CrudContext {
        return new CrudContext(
            $op,
            $resourceName,
            [],
            null,
            new FakeCrudRequest(clientIp: $clientIp),
        );
    }

    private function passThrough(): CrudHandlerInterface
    {
        return new class implements CrudHandlerInterface {
            public function handle(CrudContext $c): CrudResult { return new CrudResult(success: true); }
        };
    }

    private function rateLimiter(bool $allows = true): RateLimiterPortInterface
    {
        return new class ($allows) implements RateLimiterPortInterface {
            public ?string $lastKey = null;

            public function __construct(private bool $a) {}

            public function attempt(string $key): bool
            {
                $this->lastKey = $key;

                return $this->a;
            }

            public function remaining(string $key): int { return 0; }
            public function reset(string $key): void {}
        };
    }

    // ── Line 25: Concat — key includes both resourceName and operation ────────

    public function test_key_includes_resource_name_when_no_ip(): void
    {
        $limiter = $this->rateLimiter(true);
        $middleware = new RateLimitMiddleware($limiter); // @phpstan-ignore-line

        $middleware->process($this->context(OperationType::Create, 'orders', null), $this->passThrough());

        self::assertStringContainsString('orders', $limiter->lastKey ?? '');
    }

    public function test_key_includes_operation_value_when_no_ip(): void
    {
        $limiter = $this->rateLimiter(true);
        $middleware = new RateLimitMiddleware($limiter); // @phpstan-ignore-line

        $middleware->process($this->context(OperationType::Delete, 'products', null), $this->passThrough());

        self::assertStringContainsString('delete', $limiter->lastKey ?? '');
    }

    public function test_key_contains_separator_colon_between_resource_and_operation(): void
    {
        $limiter = $this->rateLimiter(true);
        $middleware = new RateLimitMiddleware($limiter); // @phpstan-ignore-line

        $middleware->process($this->context(OperationType::Read, 'users', null), $this->passThrough());

        self::assertSame('users:read', $limiter->lastKey);
    }

    // ── Line 25: ConcatOperandRemoval — removing one side loses information ───

    public function test_key_is_specific_to_resource_and_operation_combination(): void
    {
        // 'products:read' vs 'users:read' must produce different keys
        $limiter1 = $this->rateLimiter(true);
        $middleware1 = new RateLimitMiddleware($limiter1); // @phpstan-ignore-line
        $middleware1->process($this->context(OperationType::Read, 'products', null), $this->passThrough());

        $limiter2 = $this->rateLimiter(true);
        $middleware2 = new RateLimitMiddleware($limiter2); // @phpstan-ignore-line
        $middleware2->process($this->context(OperationType::Read, 'users', null), $this->passThrough());

        self::assertNotSame($limiter1->lastKey, $limiter2->lastKey);
    }

    public function test_key_uses_client_ip_when_available(): void
    {
        $limiter = $this->rateLimiter(true);
        $middleware = new RateLimitMiddleware($limiter); // @phpstan-ignore-line

        $middleware->process($this->context(OperationType::Read, 'products', '192.168.1.1'), $this->passThrough());

        self::assertSame('192.168.1.1', $limiter->lastKey);
    }

    // ── Throw when rate limit exceeded ────────────────────────────────────────

    public function test_rate_limited_throws_rate_limit_exception(): void
    {
        $limiter = $this->rateLimiter(false);
        $middleware = new RateLimitMiddleware($limiter); // @phpstan-ignore-line

        $this->expectException(RateLimitException::class);

        $middleware->process($this->context(), $this->passThrough());
    }

    public function test_not_rate_limited_calls_next(): void
    {
        $called = false;
        $next = new class ($called) implements CrudHandlerInterface {
            public function __construct(private bool &$c) {}
            public function handle(CrudContext $ctx): CrudResult { $this->c = true; return new CrudResult(success: true); }
        };

        $limiter = $this->rateLimiter(true);
        $middleware = new RateLimitMiddleware($limiter); // @phpstan-ignore-line

        $middleware->process($this->context(), $next);

        self::assertTrue($called);
    }
}
