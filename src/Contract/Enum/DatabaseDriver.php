<?php

declare(strict_types=1);

namespace Bamise\Contract\Enum;

enum DatabaseDriver: string
{
    case Mysql = 'mysql';
    case Postgres = 'postgres';
    case Mariadb = 'mariadb';
    case Sqlite = 'sqlite';
}
