<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Queue;

use Bamise\Contract\QueuePortInterface;

/**
 * Process-local queue for tests and development.
 */
final class InMemoryQueue implements QueuePortInterface
{
    /** @var list<array{job: string, payload: array<string, mixed>}> */
    private array $jobs = [];

    #[\Override]
    public function push(string $job, array $payload): void
    {
        $this->jobs[] = [
            'job' => $job,
            'payload' => $payload,
        ];
    }

    /**
     * @return list<array{job: string, payload: array<string, mixed>}>
     */
    public function all(): array
    {
        return $this->jobs;
    }

    public function count(): int
    {
        return count($this->jobs);
    }

    public function clear(): void
    {
        $this->jobs = [];
    }
}
