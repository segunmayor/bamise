<?php

declare(strict_types=1);

namespace Bamise\Contract\Persistence;

use PDO;

interface ConnectionInterface
{
    public function pdo(): PDO;

    public function dialect(): DatabaseDialectInterface;

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed;
}
