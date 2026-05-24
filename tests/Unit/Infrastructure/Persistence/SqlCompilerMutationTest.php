<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Persistence;

use Bamise\Infrastructure\Persistence\PDO\Dialect\SqliteDialect;
use Bamise\Infrastructure\Persistence\Query\SqlCompiler;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for SqlCompiler.
 *
 * Kills escaped mutants:
 * - Line 28: UnwrapArrayMap (column names are quoted in INSERT SQL)
 * - Line 64: Throw_ (empty update data throws)
 * - Line 72: Continue_ → break (primary key column is skipped, NOT stop)
 * - Lines 126-127: DecrementInteger/IncrementInteger on default limit=100, offset=0
 */
final class SqlCompilerMutationTest extends TestCase
{
    private SqlCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new SqlCompiler(new SqliteDialect());
    }

    // ── Line 28: UnwrapArrayMap — columns must be quoted in INSERT ────────────

    public function test_insert_sql_quotes_column_names(): void
    {
        $query = $this->compiler->compileInsert('products', 'id', ['name' => 'Widget', 'price' => 9]);

        // SqliteDialect quotes with double-quotes: "name", "price"
        self::assertStringContainsString('"name"', $query->sql);
        self::assertStringContainsString('"price"', $query->sql);
    }

    public function test_insert_sql_quotes_table_name(): void
    {
        $query = $this->compiler->compileInsert('my_table', 'id', ['col' => 1]);

        self::assertStringContainsString('"my_table"', $query->sql);
    }

    public function test_insert_sql_has_unquoted_placeholders(): void
    {
        $query = $this->compiler->compileInsert('t', 'id', ['name' => 'x']);

        // Placeholders must NOT be quoted: :name not ":name"
        self::assertStringContainsString(':name', $query->sql);
        // The placeholder string should appear without surrounding quotes
        self::assertStringNotContainsString('":name"', $query->sql);
    }

    // ── Line 64: Throw_ — empty update data throws ────────────────────────────

    public function test_compile_update_with_empty_data_throws(): void
    {
        // Using try/catch/fail instead of expectException so Infection sees a real
        // assertion failure (not just a PHPUnit "risky" marker) when the throw is removed.
        $caught = null;
        try {
            $this->compiler->compileUpdate('t', 'id', 1, []);
            self::fail('Expected InvalidArgumentException for empty data array.');
        } catch (\InvalidArgumentException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        // Must match the first guard ("at least one column") not the second ("at least one mutable column").
        self::assertMatchesRegularExpression('/Update requires at least one column\./', $caught->getMessage());
    }

    // ── Line 72: Continue_ → break — primary key skipped, others included ────

    public function test_update_sql_skips_primary_key_column(): void
    {
        $query = $this->compiler->compileUpdate('products', 'id', 5, [
            'id' => 5,
            'name' => 'Widget',
            'price' => 9,
        ]);

        // Primary key 'id' should NOT appear in SET clause (only in WHERE)
        self::assertStringContainsString('"name"', $query->sql);
        self::assertStringContainsString('"price"', $query->sql);
        // Extract SET clause (between SET and WHERE) and verify 'id' not there
        $setStart = strpos($query->sql, 'SET ') ?: 0;
        $whereStart = strpos($query->sql, ' WHERE ') ?: strlen($query->sql);
        $setClause = substr($query->sql, $setStart, $whereStart - $setStart);
        self::assertStringNotContainsString('"id"', $setClause, 'Primary key must not appear in SET clause');
    }

    public function test_update_sql_includes_all_non_pk_columns(): void
    {
        // If Continue_ is mutated to break, only columns BEFORE the pk would be in SET
        $query = $this->compiler->compileUpdate('products', 'id', 1, [
            'name' => 'A',   // before pk
            'id' => 1,       // pk — must be skipped
            'price' => 99,   // AFTER pk — must still appear (break would omit this)
        ]);

        self::assertStringContainsString('"name"', $query->sql);
        self::assertStringContainsString('"price"', $query->sql);
    }

    // ── Lines 126-127: default limit=100, offset=0 ───────────────────────────

    public function test_compile_select_all_default_sql_has_limit_100(): void
    {
        $query = $this->compiler->compileSelectAll('products');

        self::assertStringContainsString('LIMIT 100', $query->sql);
    }

    public function test_compile_select_all_default_sql_has_offset_0(): void
    {
        $query = $this->compiler->compileSelectAll('products');

        self::assertStringContainsString('OFFSET 0', $query->sql);
    }

    public function test_compile_select_all_does_not_have_limit_99(): void
    {
        $query = $this->compiler->compileSelectAll('products');

        self::assertStringNotContainsString('LIMIT 99', $query->sql);
    }

    public function test_compile_select_all_does_not_have_limit_101(): void
    {
        $query = $this->compiler->compileSelectAll('products');

        self::assertStringNotContainsString('LIMIT 101', $query->sql);
    }

    public function test_compile_select_all_does_not_have_negative_offset(): void
    {
        $query = $this->compiler->compileSelectAll('products');

        self::assertStringNotContainsString('OFFSET -1', $query->sql);
    }

    public function test_compile_select_all_does_not_have_offset_1_by_default(): void
    {
        $query = $this->compiler->compileSelectAll('products');

        self::assertStringNotContainsString('OFFSET 1', $query->sql);
    }

    public function test_compile_select_all_explicit_limit_and_offset(): void
    {
        $query = $this->compiler->compileSelectAll('products', [], 50, 10);

        self::assertStringContainsString('LIMIT 50', $query->sql);
        self::assertStringContainsString('OFFSET 10', $query->sql);
    }
}
