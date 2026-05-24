<?php

declare(strict_types=1);

namespace Bamise\Tests\Integration\Infrastructure;

use Bamise\Infrastructure\Persistence\PDO\Dialect\SqliteDialect;
use Bamise\Infrastructure\Persistence\PDO\PdoConnection;
use Bamise\Tests\Fixtures\SqliteTestConnection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for PdoConnection.
 *
 * Kills escaped mutants:
 * - Line 18: MethodCallRemoval (setAttribute ERRMODE)
 * - Line 44: IfNegation (inTransaction check)
 * - Lines 48,52,57: MethodCallRemoval (beginTransaction, commit, rollBack)
 * - Line 62: Throw_ (re-throw after failed rollback)
 */
final class PdoConnectionMutationTest extends TestCase
{
    protected function setUp(): void
    {
        if (! SqliteTestConnection::isAvailable()) {
            $this->markTestSkipped('pdo_sqlite extension not available.');
        }
    }

    private function connection(): PdoConnection
    {
        $pdo = new PDO('sqlite::memory:');

        return new PdoConnection($pdo, new SqliteDialect());
    }

    // ── Line 18: MethodCallRemoval (setAttribute ERRMODE_EXCEPTION) ───────────

    public function test_pdo_has_errmode_exception_set(): void
    {
        $conn = $this->connection();

        // If ERRMODE_EXCEPTION is not set, a bad query would return false instead of throwing
        // This test verifies an invalid query throws (ERRMODE_EXCEPTION is active)
        $this->expectException(\PDOException::class);

        $conn->pdo()->exec('SELECT * FROM nonexistent_table');
    }

    // ── Line 44: IfNegation (inTransaction check) ─────────────────────────────

    public function test_nested_transaction_does_not_start_new_transaction(): void
    {
        $conn = $this->connection();
        $conn->pdo()->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');

        // Start an outer transaction
        $conn->pdo()->beginTransaction();

        $result = $conn->transaction(function () use ($conn) {
            // inTransaction() = true → should just call callback (no beginTransaction)
            $conn->pdo()->exec("INSERT INTO t VALUES (1)");

            return 'inner-result';
        });

        // Commit manually since we started the outer transaction manually
        $conn->pdo()->commit();

        self::assertSame('inner-result', $result);
        $count = (int) $conn->pdo()->query('SELECT COUNT(*) FROM t')->fetchColumn();
        self::assertSame(1, $count);
    }

    // ── Lines 48,52: beginTransaction and commit ─────────────────────────────

    public function test_transaction_begins_and_commits(): void
    {
        $conn = $this->connection();
        $conn->pdo()->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');

        $result = $conn->transaction(function () use ($conn) {
            $conn->pdo()->exec("INSERT INTO t VALUES (1)");

            return 'done';
        });

        self::assertSame('done', $result);
        $count = (int) $conn->pdo()->query('SELECT COUNT(*) FROM t')->fetchColumn();
        self::assertSame(1, $count);
    }

    public function test_transaction_returns_callback_result(): void
    {
        $conn = $this->connection();

        $result = $conn->transaction(fn () => 42);

        self::assertSame(42, $result);
    }

    // ── Line 57: MethodCallRemoval (rollBack on exception) ───────────────────

    public function test_transaction_rolls_back_on_exception(): void
    {
        $conn = $this->connection();
        $conn->pdo()->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');

        try {
            $conn->transaction(function () use ($conn) {
                $conn->pdo()->exec("INSERT INTO t VALUES (1)");
                throw new \RuntimeException('fail');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $count = (int) $conn->pdo()->query('SELECT COUNT(*) FROM t')->fetchColumn();
        self::assertSame(0, $count); // rolled back
    }

    // ── Line 62: Throw_ (re-throw after rollback) ─────────────────────────────

    public function test_transaction_re_throws_original_exception(): void
    {
        $conn = $this->connection();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('original error');

        $conn->transaction(function () {
            throw new \RuntimeException('original error');
        });
    }

    // ── dialect() method ──────────────────────────────────────────────────────

    public function test_dialect_returns_configured_dialect(): void
    {
        $conn = $this->connection();

        self::assertInstanceOf(SqliteDialect::class, $conn->dialect());
    }

    // ── pdo() method ─────────────────────────────────────────────────────────

    public function test_pdo_returns_configured_pdo(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $conn = new PdoConnection($pdo, new SqliteDialect());

        self::assertSame($pdo, $conn->pdo());
    }
}
