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

    public function test_compile_select_all_with_no_criteria_appends_limit_offset(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());
        $query = $compiler->compileSelectAll('posts', [], 50, 10);

        self::assertSame('SELECT * FROM "posts" LIMIT 50 OFFSET 10', $query->sql);
        self::assertSame([], $query->bindings);
    }

    public function test_compile_select_all_with_criteria_adds_where_clause(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());
        $query = $compiler->compileSelectAll('posts', ['status' => 'active'], 100, 0);

        self::assertStringContainsString('WHERE "status" = :__w_status', $query->sql);
        self::assertSame('active', $query->bindings['__w_status']);
    }

    public function test_compile_update_where_with_criteria_builds_correct_sql(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());
        $query = $compiler->compileUpdateWhere('posts', ['status' => 'draft'], ['title' => 'New']);

        self::assertStringContainsString('UPDATE "posts" SET', $query->sql);
        self::assertStringContainsString('"title" = :title', $query->sql);
        self::assertStringContainsString('WHERE "status" = :__w_status', $query->sql);
        self::assertSame('New', $query->bindings['title']);
        self::assertSame('draft', $query->bindings['__w_status']);
    }

    public function test_compile_delete_where_with_criteria_builds_correct_sql(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());
        $query = $compiler->compileDeleteWhere('posts', ['status' => 'archived']);

        self::assertSame('DELETE FROM "posts" WHERE "status" = :__w_status', $query->sql);
        self::assertSame('archived', $query->bindings['__w_status']);
    }

    public function test_compile_delete_where_with_no_criteria_deletes_all(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());
        $query = $compiler->compileDeleteWhere('posts', []);

        self::assertSame('DELETE FROM "posts"', $query->sql);
        self::assertSame([], $query->bindings);
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
