<?php

declare(strict_types=1);

namespace Bamise\Application\Middleware;

use Bamise\Application\Context\CrudContextFactory;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\Security\SanitizerPortInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;

final class SanitizeMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SanitizerPortInterface $sanitizer,
        private readonly CrudContextFactory $contextFactory,
    ) {
    }

    public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
    {
        $sanitized = $this->sanitizer->sanitize($context->inputData);

        return $next->handle($this->contextFactory->withInputData($context, $sanitized));
    }
}
