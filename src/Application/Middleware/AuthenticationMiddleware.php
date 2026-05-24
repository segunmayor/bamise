<?php

declare(strict_types=1);

namespace Bamise\Application\Middleware;

use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Context\SubjectFactory;
use Bamise\Contract\AuthPortInterface;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;

final class AuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthPortInterface $auth,
        private readonly SubjectFactory $subjectFactory,
        private readonly CrudContextFactory $contextFactory,
    ) {
    }

    #[\Override]
    public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
    {
        $authSubject = $this->auth->authenticate($context->request)
            ?? $this->auth->subject();
        $subject = $this->subjectFactory->fromAuthSubject($authSubject);

        return $next->handle($this->contextFactory->withSubject($context, $subject));
    }
}
