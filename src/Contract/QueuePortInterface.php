<?php

declare(strict_types=1);

namespace Bamise\Contract;

interface QueuePortInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function push(string $job, array $payload): void;
}
