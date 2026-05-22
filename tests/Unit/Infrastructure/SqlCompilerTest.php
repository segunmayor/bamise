<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure;

use Bamise\Infrastructure\Persistence\PDO\Dialect\PostgresDialect;
use Bamise\Infrastructure\Persistence\PDO\Dialect\SqliteDialect;
use Bamise\Infrastructure\Persistence\Query\SqlCompiler;
use PHPUnit\Framework\TestCase;

final class SqlCompilerTest extends TestCase
{
    public function test_sqlite_compile_select_by_id_uses_quoted_identifiers(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());
        $query = $compiler->compileSelectById('users', 'id', 42);

        self::assertSame('SELECT * FROM "users" WHERE "id" = :__pk LIMIT 1', $query->sql);
        self::assertSame(['__pk' => 42], $query->bindings);
    }

    public function test_postgres_compile_insert_includes_returning_clause(): void
    {
        $compiler = new SqlCompiler(new PostgresDialect());
        $query = $compiler->compileInsert('users', 'id', ['name' => 'Ada', 'email' => 'a@b.c']);

        self::assertStringContainsString('INSERT INTO "users"', $query->sql);
        self::assertStringContainsString('RETURNING "id"', $query->sql);
        self::assertSame(['name' => 'Ada', 'email' => 'a@b.c'], $query->bindings);
    }

    public function test_whitelist_columns_filters_unknown_keys(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());
        $filtered = $compiler->whitelistColumns(
            ['name', 'email'],
            ['name' => 'Ada', 'is_admin' => true],
        );

        self::assertSame(['name' => 'Ada'], $filtered);
    }
}
