<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Middleware;

use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Middleware\SanitizeMiddleware;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Security\SanitizerPortInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakeSanitizerPort;
use PHPUnit\Framework\TestCase;

final class SanitizeMiddlewareTest extends TestCase
{
    public function test_passes_sanitized_input_to_next_handler(): void
    {
        $sanitizer = new class implements SanitizerPortInterface {
            public function sanitize(array $data): array
            {
                return array_map(static fn ($v) => strtoupper((string) $v), $data);
            }
        };

        $middleware = new SanitizeMiddleware($sanitizer, new CrudContextFactory());
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
            OperationType::Create,
            'users',
            ['name' => 'ada'],
            null,
            new FakeCrudRequest(),
        );

        $middleware->process($context, $next);

        self::assertSame(['name' => 'ADA'], $capturedContext->inputData);
    }

    public function test_returns_result_from_inner_handler(): void
    {
        $middleware = new SanitizeMiddleware(new FakeSanitizerPort(), new CrudContextFactory());
        $expected = new CrudResult(success: true, data: ['id' => 5]);

        $result = $middleware->process(
            new CrudContext(OperationType::Create, 'users', [], null, new FakeCrudRequest()),
            new class ($expected) implements CrudHandlerInterface {
                public function __construct(private CrudResult $result)
                {
                }
                public function handle(CrudContext $context): CrudResult
                {
                    return $this->result;
                }
            },
        );

        self::assertSame($expected, $result);
    }
}
