<?php

declare(strict_types=1);

namespace Bamise\Application\Config;

use Bamise\Contract\MiddlewareInterface;

readonly class MiddlewareConfig
{
    /**
     * @param list<array{class: class-string<MiddlewareInterface>, priority: int}> $middleware
     */
    public function __construct(
        public array $middleware,
    ) {
    }

    public static function defaults(): self
    {
        return new self([
            ['class' => \Bamise\Application\Middleware\RateLimitMiddleware::class, 'priority' => 100],
            ['class' => \Bamise\Application\Middleware\AuthenticationMiddleware::class, 'priority' => 200],
            ['class' => \Bamise\Application\Middleware\CsrfMiddleware::class, 'priority' => 300],
            ['class' => \Bamise\Application\Middleware\SanitizeMiddleware::class, 'priority' => 400],
            ['class' => \Bamise\Application\Middleware\ValidateMiddleware::class, 'priority' => 500],
            ['class' => \Bamise\Application\Middleware\AuthorizeMiddleware::class, 'priority' => 600],
            ['class' => \Bamise\Application\Middleware\AuditMiddleware::class, 'priority' => 900],
        ]);
    }
}
