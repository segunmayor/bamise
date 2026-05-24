<?php

declare(strict_types=1);

namespace Bamise\Application\Middleware;

use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Exception\AuthorizationException;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Domain\Exception\InsufficientPermissionException;
use Bamise\Domain\Model\Permission;
use Bamise\Domain\Model\Subject;
use Bamise\Domain\Policy\PolicyEvaluator;
use Bamise\Domain\Service\PermissionEvaluator;

final class AuthorizeMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly PermissionEvaluator $permissionEvaluator,
        private readonly PolicyEvaluator $policyEvaluator,
    ) {
    }

    #[\Override]
    public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
    {
        $subject = $context->subject;

        if (! $subject instanceof Subject) {
            throw new AuthorizationException('Authentication required.');
        }

        $permission = new Permission(
            $context->resourceName,
            $context->operation->value,
        );

        if (! $this->permissionEvaluator->isGranted($subject, $permission)) {
            throw new InsufficientPermissionException(
                sprintf('Permission "%s" denied.', $permission->toString()),
            );
        }

        if (! $this->policyEvaluator->evaluate(
            $subject,
            $context->operation->value,
            $context->resourceName,
        )) {
            throw new AuthorizationException(
                sprintf('Policy denied for resource "%s".', $context->resourceName),
            );
        }

        return $next->handle($context);
    }
}
