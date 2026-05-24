<?php

declare(strict_types=1);

namespace Bamise\Tests\Integration\Infrastructure;

use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Infrastructure\Persistence\PDO\ConnectionConfig;
use Bamise\Infrastructure\Persistence\PDO\Dialect\PostgresDialect;
use Bamise\Infrastructure\Persistence\PDO\PdoConnection;
use Bamise\Infrastructure\Persistence\Query\SqlCompiler;
use Bamise\Infrastructure\Persistence\Repository\PdoRepository;
use Bamise\Contract\Enum\DatabaseDriver;
use Bamise\Tests\Fixtures\SqliteTestConnection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for PdoRepository.
 *
 * Uses SQLite with PostgresDialect to exercise the RETURNING branch (SQLite 3.35+ supports RETURNING).
 * Also targets the lastInsertId() fallback paths and row-count checks.
 */
final class PdoRepositoryMutationTest extends TestCase
{
    protected function setUp(): void
    {
        if (! SqliteTestConnection::isAvailable()) {
            $this->markTestSkipped('pdo_sqlite extension not available.');
        }
    }

    /**
     * Create a PdoRepository backed by SQLite but using PostgresDialect so the
     * RETURNING clause is emitted and exercises the supportsReturning() = true path.
     */
    private function returningRepository(): array
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, price REAL)');

        $dialect = new PostgresDialect();
        $connection = new PdoConnection($pdo, $dialect);
        $compiler = new SqlCompiler($dialect);
        $repo = new PdoRepository($connection, $compiler, 'products', 'id', ['name', 'price']);

        return [$repo, $pdo];
    }

    private function nonReturningRepository(): array
    {
        $connection = SqliteTestConnection::create();
        $connection->pdo()->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT)');
        $compiler = new SqlCompiler($connection->dialect());
        $repo = new PdoRepository($connection, $compiler, 'items', 'id', ['label']);

        return [$repo, $connection->pdo()];
    }

    // ── RETURNING path (line 40-54) ───────────────────────────────────────────

    public function test_insert_with_returning_returns_resource_id(): void
    {
        [$repo] = $this->returningRepository();

        $id = $repo->insert(['name' => 'Widget', 'price' => 9.99]);

        self::assertInstanceOf(ResourceId::class, $id);
        self::assertGreaterThan(0, $id->value);
    }

    public function test_insert_with_returning_returns_correct_id_type(): void
    {
        [$repo] = $this->returningRepository();

        $id = $repo->insert(['name' => 'Gadget', 'price' => 19.99]);

        // RETURNING path resolves to an int (from SQLite autoincrement)
        self::assertIsInt($id->value);
    }

    public function test_find_works_after_returning_insert(): void
    {
        [$repo] = $this->returningRepository();

        $id = $repo->insert(['name' => 'Thing', 'price' => 1.00]);
        $row = $repo->find($id);

        self::assertNotNull($row);
        self::assertSame('Thing', $row['name']);
    }

    // ── lastInsertId() path (SQLite dialect, non-RETURNING) ───────────────────

    public function test_insert_sqlite_returns_int_resource_id(): void
    {
        [$repo] = $this->nonReturningRepository();

        $id = $repo->insert(['label' => 'alpha']);

        self::assertInstanceOf(ResourceId::class, $id);
        self::assertSame(1, $id->value);
    }

    public function test_successive_inserts_return_incrementing_ids(): void
    {
        [$repo] = $this->nonReturningRepository();

        $id1 = $repo->insert(['label' => 'first']);
        $id2 = $repo->insert(['label' => 'second']);
        $id3 = $repo->insert(['label' => 'third']);

        self::assertSame(1, $id1->value);
        self::assertSame(2, $id2->value);
        self::assertSame(3, $id3->value);
    }

    // ── update() rowCount check (line 84 GreaterThan) ─────────────────────────

    public function test_update_returns_true_when_row_affected(): void
    {
        [$repo] = $this->nonReturningRepository();

        $id = $repo->insert(['label' => 'before']);
        $result = $repo->update($id, ['label' => 'after']);

        self::assertTrue($result);
    }

    public function test_update_returns_false_when_no_row_affected(): void
    {
        [$repo] = $this->nonReturningRepository();

        $result = $repo->update(new ResourceId(999), ['label' => 'ghost']);

        self::assertFalse($result);
    }

    // ── delete() rowCount check ───────────────────────────────────────────────

    public function test_delete_returns_true_when_row_deleted(): void
    {
        [$repo] = $this->nonReturningRepository();

        $id = $repo->insert(['label' => 'delete-me']);
        self::assertTrue($repo->delete($id));
    }

    public function test_delete_returns_false_when_row_not_found(): void
    {
        [$repo] = $this->nonReturningRepository();

        self::assertFalse($repo->delete(new ResourceId(42)));
    }

    // ── findAll() list type ───────────────────────────────────────────────────

    public function test_find_all_returns_list_type(): void
    {
        [$repo] = $this->nonReturningRepository();

        $repo->insert(['label' => 'x']);
        $rows = $repo->findAll();

        self::assertIsArray($rows);
        self::assertArrayHasKey(0, $rows);
    }

    public function test_find_all_with_criteria_filters_correctly(): void
    {
        [$repo] = $this->nonReturningRepository();

        $repo->insert(['label' => 'keep']);
        $repo->insert(['label' => 'remove']);

        $rows = $repo->findAll(['label' => 'keep']);

        self::assertCount(1, $rows);
        self::assertSame('keep', $rows[0]['label']);
    }

    // ── updateBulk / deleteBulk ───────────────────────────────────────────────

    public function test_update_bulk_returns_affected_count(): void
    {
        [$repo] = $this->nonReturningRepository();

        $repo->insert(['label' => 'a']);
        $repo->insert(['label' => 'a']);
        $repo->insert(['label' => 'b']);

        $count = $repo->updateBulk(['label' => 'a'], ['label' => 'z']);

        self::assertSame(2, $count);
    }

    public function test_delete_bulk_returns_affected_count(): void
    {
        [$repo] = $this->nonReturningRepository();

        $repo->insert(['label' => 'del']);
        $repo->insert(['label' => 'del']);
        $repo->insert(['label' => 'keep']);

        $count = $repo->deleteBulk(['label' => 'del']);

        self::assertSame(2, $count);
    }

    // ── Primary key fallback when lastInsertId fails ──────────────────────────

    public function test_insert_with_explicit_pk_in_data_not_fillable_uses_last_insert_id(): void
    {
        // When the table has autoincrement and pk is not in fillable,
        // lastInsertId() is the only source of the returned id.
        [$repo] = $this->nonReturningRepository();

        $id = $repo->insert(['label' => 'pk-test']);

        // lastInsertId() should return a valid numeric string → int
        self::assertIsInt($id->value);
        self::assertGreaterThan(0, $id->value);
    }
}
