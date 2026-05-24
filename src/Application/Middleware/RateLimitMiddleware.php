<?php

declare(strict_types=1);

namespace Bamise\Application\Middleware;

use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Exception\RateLimitException;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\Security\RateLimiterPortInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiterPortInterface $rateLimiter,
    ) {
    }

    #[\Override]
    public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
    {
        $key = $context->request->clientIp()
            ?? $context->resourceName . ':' . $context->operation->value;

        if (! $this->rateLimiter->attempt($key)) {
            throw new RateLimitException(
                sprintf('Rate limit exceeded for key "%s".', $key),
            );
        }

        return $next->handle($context);
    }
}
