<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Persistence\PDO\Dialect;

use Bamise\Contract\Enum\DatabaseDriver;

final class MariadbDialect extends MysqlDialect
{
    public function driver(): DatabaseDriver
    {
        return DatabaseDriver::Mariadb;
    }
}
