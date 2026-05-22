<?php

declare(strict_types=1);

namespace Bamise\Application\Middleware;

use Bamise\Contract\MiddlewareInterface;

readonly class PrioritizedMiddleware
{
    public function __construct(
        public MiddlewareInterface $middleware,
        public int $priority,
    ) {
    }
}
