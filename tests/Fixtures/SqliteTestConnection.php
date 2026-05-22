<?php

declare(strict_types=1);

namespace Bamise\Tests\Fixtures;

use Bamise\Contract\Enum\DatabaseDriver;
use Bamise\Contract\Persistence\ConnectionInterface;
use Bamise\Infrastructure\Persistence\PDO\ConnectionConfig;
use Bamise\Infrastructure\Persistence\PDO\PdoConnection;

final class SqliteTestConnection
{
    public static function isAvailable(): bool
    {
        return extension_loaded('pdo_sqlite');
    }

    public static function create(): ConnectionInterface
    {
        if (! self::isAvailable()) {
            throw new \RuntimeException('pdo_sqlite extension is not available.');
        }

        return PdoConnection::fromConfig(
            new ConnectionConfig(
                dsn: 'sqlite::memory:',
                user: '',
                password: '',
                driver: DatabaseDriver::Sqlite,
            ),
        );
    }
}
