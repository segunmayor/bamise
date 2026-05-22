<?php

declare(strict_types=1);

namespace Bamise\Contract\Persistence;

use Bamise\Contract\Enum\DatabaseDriver;

interface DatabaseDialectInterface
{
    public function quoteIdentifier(string $identifier): string;

    public function supportsReturning(): bool;

    public function driver(): DatabaseDriver;
}
