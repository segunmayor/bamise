<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Event;

use Bamise\Contract\Event\DomainEventInterface;
use Bamise\Contract\ValueObject\CrudContext;
use InvalidArgumentException;

final class EventPayloadEncoder
{
    /**
     * @return array<string, mixed>
     */
    public function encode(object $event): array
    {
        if (! $event instanceof DomainEventInterface) {
            throw new InvalidArgumentException(
                sprintf('Cannot encode event of type "%s" for queue dispatch.', get_class($event)),
            );
        }

        $context = $this->extractContext($event);

        return [
            'event_class' => get_class($event),
            'operation' => $context->operation->value,
            'resource_name' => $context->resourceName,
            'input_data' => $context->inputData,
            'payload' => $this->extractPayload($event),
        ];
    }

    private function extractContext(object $event): CrudContext
    {
        if (! property_exists($event, 'context') || ! $event->context instanceof CrudContext) {
            throw new InvalidArgumentException(
                sprintf('Event "%s" has no CrudContext for queue encoding.', get_class($event)),
            );
        }

        return $event->context;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractPayload(object $event): ?array
    {
        if (! property_exists($event, 'payload')) {
            return null;
        }

        $payload = $event->payload;

        if (! is_array($payload)) {
            return null;
        }

        $result = [];

        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
