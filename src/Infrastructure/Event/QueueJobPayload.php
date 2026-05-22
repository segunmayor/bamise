<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Event;

readonly class QueueJobPayload
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $job,
        public array $payload,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'job' => $this->job,
            'payload' => $this->payload,
        ];
    }
}
