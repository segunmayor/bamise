<?php

declare(strict_types=1);

namespace Bamise\Application\Context;

use Bamise\Contract\Http\CrudRequestInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Domain\Model\FieldBag;
use Bamise\Domain\Model\ResolvedOperation;
use Bamise\Domain\Model\Subject;

final class CrudContextFactory
{
    public function create(
        ResolvedOperation $resolved,
        CrudRequestInterface $request,
        ?Subject $subject = null,
        ?FieldBag $data = null,
    ): CrudContext {
        return new CrudContext(
            operation: $resolved->operation,
            resourceName: $resolved->resource->name,
            inputData: $data !== null ? $data->all() : $request->input(),
            subject: $subject,
            request: $request,
        );
    }

    public function fromState(PipelineState $state): CrudContext
    {
        return $this->withSubject(
            $state->context,
            $state->subject,
        );
    }

    public function withSubject(CrudContext $context, ?Subject $subject): CrudContext
    {
        return new CrudContext(
            $context->operation,
            $context->resourceName,
            $context->inputData,
            $subject,
            $context->request,
        );
    }

    /**
     * @param array<string, mixed> $inputData
     */
    public function withInputData(CrudContext $context, array $inputData): CrudContext
    {
        return new CrudContext(
            $context->operation,
            $context->resourceName,
            $inputData,
            $context->subject,
            $context->request,
        );
    }
}
