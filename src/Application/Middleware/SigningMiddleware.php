<?php

declare(strict_types=1);

namespace Bamise\Application\Middleware;

use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Exception\AuthorizationException;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\Security\RequestSignerPortInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;

final class SigningMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RequestSignerPortInterface $signer,
    ) {
    }

    public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
    {
        if (! $this->signer->verify($context->request)) {
            throw new AuthorizationException('Request signature is invalid or missing.');
        }

        return $next->handle($context);
    }
}
