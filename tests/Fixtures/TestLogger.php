<?php

declare(strict_types=1);

namespace Bamise\Tests\Fixtures;

use Psr\Log\AbstractLogger;

final class TestLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $entries = [];

    public function log($level, $message, array $context = []): void
    {
        $this->entries[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
