<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application;

use Bamise\Application\Config\MiddlewareConfig;
use Bamise\Application\Middleware\AuditMiddleware;
use Bamise\Application\Middleware\AuthenticationMiddleware;
use Bamise\Application\Middleware\AuthorizeMiddleware;
use Bamise\Application\Middleware\CsrfMiddleware;
use Bamise\Application\Middleware\RateLimitMiddleware;
use Bamise\Application\Middleware\SanitizeMiddleware;
use Bamise\Application\Middleware\ValidateMiddleware;
use PHPUnit\Framework\TestCase;

final class MiddlewareConfigTest extends TestCase
{
    public function test_defaults_has_seven_entries(): void
    {
        $config = MiddlewareConfig::defaults();

        self::assertCount(7, $config->middleware);
    }

    public function test_defaults_contains_all_standard_middleware_classes(): void
    {
        $config = MiddlewareConfig::defaults();
        $classes = array_column($config->middleware, 'class');

        self::assertContains(RateLimitMiddleware::class, $classes);
        self::assertContains(AuthenticationMiddleware::class, $classes);
        self::assertContains(CsrfMiddleware::class, $classes);
        self::assertContains(SanitizeMiddleware::class, $classes);
        self::assertContains(ValidateMiddleware::class, $classes);
        self::assertContains(AuthorizeMiddleware::class, $classes);
        self::assertContains(AuditMiddleware::class, $classes);
    }

    public function test_default_priorities_are_in_strictly_ascending_order(): void
    {
        $config = MiddlewareConfig::defaults();
        $priorities = array_column($config->middleware, 'priority');
        $sorted = $priorities;
        sort($sorted);

        self::assertSame($sorted, $priorities);
    }

    public function test_custom_config_with_empty_array_is_valid(): void
    {
        $config = new MiddlewareConfig([]);

        self::assertSame([], $config->middleware);
    }
}
