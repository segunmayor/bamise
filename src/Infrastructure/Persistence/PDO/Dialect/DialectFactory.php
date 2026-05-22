<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Persistence\PDO\Dialect;

use Bamise\Contract\Enum\DatabaseDriver;
use Bamise\Contract\Persistence\DatabaseDialectInterface;

final class DialectFactory
{
    public static function fromDriver(DatabaseDriver $driver): DatabaseDialectInterface
    {
        return match ($driver) {
            DatabaseDriver::Mysql    => new MysqlDialect(),
            DatabaseDriver::Mariadb  => new MariadbDialect(),
            DatabaseDriver::Postgres => new PostgresDialect(),
            DatabaseDriver::Sqlite   => new SqliteDialect(),
        };
    }
}
