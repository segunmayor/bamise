<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Persistence\PDO;

use Bamise\Contract\Enum\DatabaseDriver;

readonly class ConnectionConfig
{
    public function __construct(
        public string $dsn,
        public string $user,
        public string $password,
        public DatabaseDriver $driver,
    ) {
    }
}
