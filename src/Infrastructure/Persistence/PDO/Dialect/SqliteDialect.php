<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Persistence\PDO\Dialect;

use Bamise\Contract\Enum\DatabaseDriver;
use Bamise\Contract\Persistence\DatabaseDialectInterface;

final class SqliteDialect implements DatabaseDialectInterface
{
    #[\Override]
    public function quoteIdentifier(string $identifier): string
    {
        $this->assertValidIdentifier($identifier);

        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    #[\Override]
    public function supportsReturning(): bool
    {
        return false;
    }

    #[\Override]
    public function driver(): DatabaseDriver
    {
        return DatabaseDriver::Sqlite;
    }

    private function assertValidIdentifier(string $identifier): void
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid SQL identifier "%s".', $identifier),
            );
        }
    }
}
