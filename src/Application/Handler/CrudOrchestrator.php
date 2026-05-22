<?php

declare(strict_types=1);

namespace Bamise\Application\Handler;

use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\EventDispatcherPortInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Domain\Event\LifecycleEventFactory;

final class CrudOrchestrator implements CrudHandlerInterface
{
    public function __construct(
        private readonly EventDispatcherPortInterface $eventDispatcher,
        private readonly LifecycleEventFactory $lifecycleEventFactory,
        private readonly CrudHandlerInterface $inner,
    ) {
    }

    public function handle(CrudContext $context): CrudResult
    {
        if (! $this->hasLifecycleEvents($context->operation)) {
            return $this->inner->handle($context);
        }

        $this->eventDispatcher->dispatch(
            $this->lifecycleEventFactory->before($context),
        );

        $result = $this->inner->handle($context);

        if ($result->success) {
            $this->eventDispatcher->dispatch(
                $this->lifecycleEventFactory->after($context, $result->data),
            );
        }

        return $result;
    }

    private function hasLifecycleEvents(OperationType $operation): bool
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
