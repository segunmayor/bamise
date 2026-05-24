<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Persistence\Dialect;

use Bamise\Contract\Enum\DatabaseDriver;
use Bamise\Infrastructure\Persistence\PDO\Dialect\DialectFactory;
use Bamise\Infrastructure\Persistence\PDO\Dialect\MariadbDialect;
use Bamise\Infrastructure\Persistence\PDO\Dialect\MysqlDialect;
use Bamise\Infrastructure\Persistence\PDO\Dialect\PostgresDialect;
use Bamise\Infrastructure\Persistence\PDO\Dialect\SqliteDialect;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DialectTest extends TestCase
{
    // ── DialectFactory ────────────────────────────────────────────────────────

    public function test_factory_returns_mysql_dialect(): void
    {
        $dialect = DialectFactory::fromDriver(DatabaseDriver::Mysql);

        self::assertInstanceOf(MysqlDialect::class, $dialect);
        self::assertSame(DatabaseDriver::Mysql, $dialect->driver());
    }

    public function test_factory_returns_mariadb_dialect(): void
    {
        $dialect = DialectFactory::fromDriver(DatabaseDriver::Mariadb);

        self::assertInstanceOf(MariadbDialect::class, $dialect);
        self::assertSame(DatabaseDriver::Mariadb, $dialect->driver());
    }

    public function test_factory_returns_postgres_dialect(): void
    {
        $dialect = DialectFactory::fromDriver(DatabaseDriver::Postgres);

        self::assertInstanceOf(PostgresDialect::class, $dialect);
        self::assertSame(DatabaseDriver::Postgres, $dialect->driver());
    }

    public function test_factory_returns_sqlite_dialect(): void
    {
        $dialect = DialectFactory::fromDriver(DatabaseDriver::Sqlite);

        self::assertInstanceOf(SqliteDialect::class, $dialect);
        self::assertSame(DatabaseDriver::Sqlite, $dialect->driver());
    }

    // ── MysqlDialect ──────────────────────────────────────────────────────────

    public function test_mysql_quotes_with_backticks(): void
    {
        $dialect = new MysqlDialect();

        self::assertSame('`users`', $dialect->quoteIdentifier('users'));
    }

    public function test_mysql_rejects_identifier_with_backtick(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new MysqlDialect())->quoteIdentifier('table`name');
    }

    public function test_mysql_does_not_support_returning(): void
    {
        self::assertFalse((new MysqlDialect())->supportsReturning());
    }

    public function test_mysql_rejects_invalid_identifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new MysqlDialect())->quoteIdentifier('bad-name');
    }

    public function test_mysql_rejects_identifier_starting_with_digit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new MysqlDialect())->quoteIdentifier('1column');
    }

    // ── MariadbDialect ────────────────────────────────────────────────────────

    public function test_mariadb_extends_mysql_quoting(): void
    {
        $dialect = new MariadbDialect();

        self::assertSame('`column_name`', $dialect->quoteIdentifier('column_name'));
    }

    public function test_mariadb_does_not_support_returning(): void
    {
        self::assertFalse((new MariadbDialect())->supportsReturning());
    }

    public function test_mariadb_driver_is_mariadb(): void
    {
        self::assertSame(DatabaseDriver::Mariadb, (new MariadbDialect())->driver());
    }

    // ── PostgresDialect ───────────────────────────────────────────────────────

    public function test_postgres_quotes_with_double_quotes(): void
    {
        $dialect = new PostgresDialect();

        self::assertSame('"users"', $dialect->quoteIdentifier('users'));
    }

    public function test_postgres_rejects_identifier_with_double_quote(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PostgresDialect())->quoteIdentifier('table"name');
    }

    public function test_postgres_supports_returning(): void
    {
        self::assertTrue((new PostgresDialect())->supportsReturning());
    }

    public function test_postgres_rejects_invalid_identifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PostgresDialect())->quoteIdentifier('bad name');
    }

    // ── SqliteDialect ─────────────────────────────────────────────────────────

    public function test_sqlite_quotes_with_double_quotes(): void
    {
        $dialect = new SqliteDialect();

        self::assertSame('"column"', $dialect->quoteIdentifier('column'));
    }

    public function test_sqlite_rejects_identifier_with_double_quote(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SqliteDialect())->quoteIdentifier('col"name');
    }

    public function test_sqlite_does_not_support_returning(): void
    {
        self::assertFalse((new SqliteDialect())->supportsReturning());
    }

    public function test_sqlite_rejects_identifier_with_special_chars(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SqliteDialect())->quoteIdentifier('col; DROP TABLE');
    }

    // ── identifier edge cases shared across dialects ──────────────────────────

    /** @return array<string, array{string}> */
    public static function validIdentifiers(): array
    {
        return [
            'simple'        => ['users'],
            'with_number'   => ['col1'],
            'underscore'    => ['_private'],
            'upper'         => ['MyTable'],
            'long'          => ['a_very_long_column_name_that_is_still_valid'],
        ];
    }

    #[DataProvider('validIdentifiers')]
    public function test_mysql_accepts_valid_identifiers(string $id): void
    {
        self::assertNotEmpty((new MysqlDialect())->quoteIdentifier($id));
    }

    #[DataProvider('validIdentifiers')]
    public function test_postgres_accepts_valid_identifiers(string $id): void
    {
        self::assertNotEmpty((new PostgresDialect())->quoteIdentifier($id));
    }

    #[DataProvider('validIdentifiers')]
    public function test_sqlite_accepts_valid_identifiers(string $id): void
    {
        self::assertNotEmpty((new SqliteDialect())->quoteIdentifier($id));
    }
}
