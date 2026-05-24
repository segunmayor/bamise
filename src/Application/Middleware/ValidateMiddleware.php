<?php

declare(strict_types=1);

namespace Bamise\Application\Middleware;

use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Exception\ValidationException;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\ValidatorPortInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Domain\Model\FieldBag;
use Bamise\Domain\Service\FillableGuard;

final class ValidateMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ValidatorPortInterface $validator,
        private readonly ResourceRegistry $resourceRegistry,
        private readonly FillableGuard $fillableGuard,
        private readonly CrudContextFactory $contextFactory,
    ) {
    }

    #[\Override]
    public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
    {
        $definition = $this->resourceRegistry->get($context->resourceName);
        $filtered = $this->fillableGuard->filter(
            $context->inputData,
            $definition->fillable(),
            $definition->guarded(),
        );
        $fieldBag = new FieldBag($filtered);
        $rules = $definition->rules($context->operation);
        $validation = $this->validator->validate($fieldBag->all(), $rules);

        if (! $validation->valid) {
            throw new ValidationException('Validation failed.');
        }

        $validatedData = $validation->sanitizedData !== []
            ? $validation->sanitizedData
            : $fieldBag->all();

        return $next->handle(
            $this->contextFactory->withInputData($context, $validatedData),
        );
    }
}
