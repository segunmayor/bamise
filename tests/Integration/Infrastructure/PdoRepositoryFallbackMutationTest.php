<?php

declare(strict_types=1);

namespace Bamise\Tests\Integration\Infrastructure;

use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Infrastructure\Persistence\PDO\Dialect\PostgresDialect;
use Bamise\Infrastructure\Persistence\PDO\PdoConnection;
use Bamise\Infrastructure\Persistence\Query\SqlCompiler;
use Bamise\Infrastructure\Persistence\Repository\PdoRepository;
use Bamise\Tests\Fixtures\SqliteTestConnection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for PdoRepository edge cases.
 *
 * Kills escaped mutants:
 * - Line 51: LogicalNot (string pk from RETURNING is valid)
 * - Line 62: IfNegation (when lastInsertId='0', use pk from data)
 * - Line 65: LogicalNot variants (string pkFallback is valid type)
 * - Line 96: DecrementInteger/IncrementInteger on default findAll limit=100
 */
final class PdoRepositoryFallbackMutationTest extends TestCase
{
    protected function setUp(): void
    {
        if (! SqliteTestConnection::isAvailable()) {
            $this->markTestSkipped('pdo_sqlite extension not available.');
        }
    }

    // ── Line 51: string pk from RETURNING path is valid ───────────────────────
    // (LogicalNot mutation: !is_string would throw on valid string pk)

    public function test_returning_path_accepts_string_primary_key(): void
    {
        // Use TEXT primary key with RETURNING dialect
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // TEXT primary key — RETURNING yields a string value
        $pdo->exec('CREATE TABLE tokens (token TEXT PRIMARY KEY, name TEXT)');

        $dialect = new PostgresDialect(); // supportsReturning() = true
        $connection = new PdoConnection($pdo, $dialect);
        $compiler = new SqlCompiler($dialect);
        // fillable includes 'token' so it can be inserted
        $repo = new PdoRepository($connection, $compiler, 'tokens', 'token', ['token', 'name']);

        // Insert a row where the pk is a string — RETURNING yields string pk
        $id = $repo->insert(['token' => 'abc-123', 'name' => 'Alice']);

        // Original: is_string('abc-123') → true → no throw → returns ResourceId('abc-123')
        // LogicalNot mutant: is_string would throw when pk IS a string
        self::assertInstanceOf(ResourceId::class, $id);
        self::assertSame('abc-123', $id->value);
    }

    // ── Line 62: IfNegation — fallback uses pk from data when lastInsertId='0' ─
    // ── Line 65: LogicalNot — string pkFallback is valid ──────────────────────

    public function test_without_rowid_table_falls_back_to_pk_in_data(): void
    {
        // WITHOUT ROWID tables cause PDO::lastInsertId() to return '0' in SQLite.
        // This exercises the fallback branch: lines 61-69.
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // WITHOUT ROWID → lastInsertId() returns '0'
        $pdo->exec('CREATE TABLE tokens (token TEXT PRIMARY KEY, label TEXT) WITHOUT ROWID');

        $conn = SqliteTestConnection::create();
        $dialect = $conn->dialect(); // SqliteDialect (supportsReturning = false)
        $connection = new PdoConnection($pdo, $dialect);
        $compiler = new SqlCompiler($dialect);
        // fillable includes 'token' so it can be in data AND used as pk fallback
        $repo = new PdoRepository($connection, $compiler, 'tokens', 'token', ['token', 'label']);

        // lastInsertId() returns '0' → code falls back to data['token'] = 'abc-001'
        $id = $repo->insert(['token' => 'abc-001', 'label' => 'Test']);

        // IfNegation mutant: inverts array_key_exists check → throws instead of using fallback
        // LogicalNot mutant: throws when pkFallback is a string (valid type)
        self::assertInstanceOf(ResourceId::class, $id);
        self::assertSame('abc-001', $id->value);
    }

    // ── Line 96: findAll() default limit=100, not 99 or 101 ──────────────────

    public function test_find_all_default_limit_is_exactly_100(): void
    {
        $connection = SqliteTestConnection::create();
        $connection->pdo()->exec('CREATE TABLE many (id INTEGER PRIMARY KEY AUTOINCREMENT, n INTEGER)');
        $compiler = new SqlCompiler($connection->dialect());
        $repo = new PdoRepository($connection, $compiler, 'many', 'id', ['n']);

        // Insert 101 rows
        for ($i = 1; $i <= 101; $i++) {
            $connection->pdo()->exec("INSERT INTO many (n) VALUES ($i)");
        }

        // findAll() with NO arguments — uses default limit=100
        $rows = $repo->findAll();

        // Default limit=100: returns exactly 100 rows (not 99 or 101)
        self::assertCount(100, $rows, 'findAll() default limit must be exactly 100');
    }

    public function test_find_all_default_starts_at_first_row(): void
    {
        $connection = SqliteTestConnection::create();
        $connection->pdo()->exec('CREATE TABLE seq (id INTEGER PRIMARY KEY AUTOINCREMENT, val INTEGER)');
        $compiler = new SqlCompiler($connection->dialect());
        $repo = new PdoRepository($connection, $compiler, 'seq', 'id', ['val']);

        $connection->pdo()->exec("INSERT INTO seq (val) VALUES (10)");
        $connection->pdo()->exec("INSERT INTO seq (val) VALUES (20)");
        $connection->pdo()->exec("INSERT INTO seq (val) VALUES (30)");

        // findAll() with no args should start at offset=0 (first row)
        $rows = $repo->findAll();

        self::assertCount(3, $rows);
        // The first row (val=10) should be included — offset=1 mutation would skip it
        $vals = array_column($rows, 'val');
        self::assertContains(10, $vals, 'Default offset must be 0, returning first row');
    }
}
