<?php

declare(strict_types=1);

namespace Bamise\Application\Context;

use Bamise\Contract\Crud\ResourceDefinitionInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Domain\Model\ResolvedOperation;
use Bamise\Domain\Model\Subject;

/**
 * Mutable pipeline accumulator around immutable {@see CrudContext}.
 * Middleware rebuilds context via {@see CrudContextFactory}; this object
 * carries resolved operation and resource definition alongside the context.
 */
readonly class PipelineState
{
    public function __construct(
        public CrudContext $context,
        public ResolvedOperation $resolvedOperation,
        public ResourceDefinitionInterface $resourceDefinition,
        public ?Subject $subject = null,
    ) {
    }

    public function withContext(CrudContext $context): self
    {
        return new self(
            $context,
            $this->resolvedOperation,
            $this->resourceDefinition,
            $this->subject,
        );
    }

    public function withSubject(?Subject $subject): self
    {
        return new self(
            $this->context,
            $this->resolvedOperation,
            $this->resourceDefinition,
            $subject,
        );
    }

    public function withSanitizedData(array $inputData): self
    {
        return new self(
            new CrudContext(
                $this->context->operation,
                $this->context->resourceName,
                $inputData,
                $this->context->subject,
                $this->context->request,
            ),
            $this->resolvedOperation,
            $this->resourceDefinition,
            $this->subject,
        );
    }
}
