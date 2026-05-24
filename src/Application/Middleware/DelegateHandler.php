<?php

declare(strict_types=1);

namespace Bamise\Application\Middleware;

use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;

final class DelegateHandler implements CrudHandlerInterface
{
    public function __construct(
        private readonly MiddlewareInterface $middleware,
        private readonly CrudHandlerInterface $next,
    ) {
    }

    #[\Override]
    public function handle(CrudContext $context): CrudResult
    {
        return $this->middleware->process($context, $this->next);
    }
}
