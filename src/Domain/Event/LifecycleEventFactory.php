<?php

declare(strict_types=1);

namespace Bamise\Domain\Event;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Event\AfterCreate;
use Bamise\Contract\Event\AfterDelete;
use Bamise\Contract\Event\AfterUpdate;
use Bamise\Contract\Event\BeforeCreate;
use Bamise\Contract\Event\BeforeDelete;
use Bamise\Contract\Event\BeforeUpdate;
use Bamise\Contract\Event\DomainEventInterface;
use Bamise\Contract\ValueObject\CrudContext;
use InvalidArgumentException;

final class LifecycleEventFactory
{
    /**
     * @param array<string, mixed>|null $payload
     */
    public function before(CrudContext $context, ?array $payload = null): DomainEventInterface
    {
        return match ($context->operation) {
            OperationType::Create => new BeforeCreate($context, $payload),
            OperationType::Update, OperationType::BulkUpdate => new BeforeUpdate($context, $payload),
            OperationType::Delete, OperationType::BulkDelete => new BeforeDelete($context, $payload),
            default => throw new InvalidArgumentException(
                sprintf('No before-event for operation "%s".', $context->operation->value),
            ),
        };
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function after(CrudContext $context, ?array $payload = null): DomainEventInterface
    {
        return match ($context->operation) {
            OperationType::Create => new AfterCreate($context, $payload),
            OperationType::Update, OperationType::BulkUpdate => new AfterUpdate($context, $payload),
            OperationType::Delete, OperationType::BulkDelete => new AfterDelete($context, $payload),
            default => throw new InvalidArgumentException(
                sprintf('No after-event for operation "%s".', $context->operation->value),
            ),
        };
    }

    public function beforeCreate(CrudContext $context, ?array $payload = null): BeforeCreate
    {
        return new BeforeCreate($context, $payload);
    }

    public function afterCreate(CrudContext $context, ?array $payload = null): AfterCreate
    {
        return new AfterCreate($context, $payload);
    }

    public function beforeUpdate(CrudContext $context, ?array $payload = null): BeforeUpdate
    {
        return new BeforeUpdate($context, $payload);
    }

    public function afterUpdate(CrudContext $context, ?array $payload = null): AfterUpdate
    {
        return new AfterUpdate($context, $payload);
    }

    public function beforeDelete(CrudContext $context, ?array $payload = null): BeforeDelete
    {
        return new BeforeDelete($context, $payload);
    }

    public function afterDelete(CrudContext $context, ?array $payload = null): AfterDelete
    {
        return new AfterDelete($context, $payload);
    }
}
