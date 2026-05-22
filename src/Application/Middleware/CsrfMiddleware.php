<?php

declare(strict_types=1);

namespace Bamise\Application\Middleware;

use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Exception\CsrfException;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\Security\CsrfPortInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly CsrfPortInterface $csrf,
    ) {
    }

    public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
    {
        if (! $this->isMutating($context->operation)) {
            return $next->handle($context);
        }

        if (! $this->csrf->validate($context->request)) {
            throw new CsrfException('CSRF token validation failed.');
        }

        return $next->handle($context);
    }

    private function isMutating(OperationType $operation): bool
    {
        return match ($operation) {
            OperationType::Create,
            OperationType::Update,
            OperationType::Delete,
            OperationType::BulkUpdate,
            OperationType::BulkDelete => true,
            default => false,
        };
    }
}
