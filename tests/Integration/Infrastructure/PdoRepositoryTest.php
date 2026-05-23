<?php

declare(strict_types=1);

namespace Bamise\Tests\Integration\Infrastructure;

use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Infrastructure\Persistence\Query\SqlCompiler;
use Bamise\Infrastructure\Persistence\Repository\PdoRepository;
use Bamise\Tests\Fixtures\SqliteTestConnection;
use PHPUnit\Framework\TestCase;

final class PdoRepositoryTest extends TestCase
{
    private PdoRepository $repository;

    protected function setUp(): void
    {
        if (! SqliteTestConnection::isAvailable()) {
            self::markTestSkipped('pdo_sqlite extension is not available.');
        }

        $connection = SqliteTestConnection::create();
        $connection->pdo()->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL
            )',
        );

        $this->repository = new PdoRepository(
            $connection,
            new SqlCompiler($connection->dialect()),
            'users',
            'id',
            ['name', 'email'],
        );
    }

    public function test_insert_find_update_delete_round_trip(): void
    {
        $id = $this->repository->insert([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);

        self::assertInstanceOf(ResourceId::class, $id);
        self::assertGreaterThan(0, $id->value);

        $found = $this->repository->find($id);
        self::assertNotNull($found);
        self::assertSame('Ada Lovelace', $found['name']);
        self::assertSame('ada@example.com', $found['email']);

        $updated = $this->repository->update($id, ['name' => 'Ada L.']);
        self::assertTrue($updated);

        $afterUpdate = $this->repository->find($id);
        self::assertNotNull($afterUpdate);
        self::assertSame('Ada L.', $afterUpdate['name']);
        self::assertSame('ada@example.com', $afterUpdate['email']);

        $deleted = $this->repository->delete($id);
        self::assertTrue($deleted);
        self::assertNull($this->repository->find($id));
    }

    public function test_whitelist_strips_non_fillable_columns_on_insert(): void
    {
        $id = $this->repository->insert([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'is_admin' => true,
        ]);

        $found = $this->repository->find($id);
        self::assertNotNull($found);
        self::assertArrayNotHasKey('is_admin', $found);
    }

    public function test_find_all_returns_all_rows_without_criteria(): void
    {
        $this->repository->insert(['name' => 'Ada', 'email' => 'ada@example.com']);
        $this->repository->insert(['name' => 'Grace', 'email' => 'grace@example.com']);

        $rows = $this->repository->findAll();

        self::assertCount(2, $rows);
        self::assertSame('Ada', $rows[0]['name']);
        self::assertSame('Grace', $rows[1]['name']);
    }

    public function test_find_all_respects_limit_and_offset(): void
    {
        $this->repository->insert(['name' => 'Ada', 'email' => 'ada@example.com']);
        $this->repository->insert(['name' => 'Grace', 'email' => 'grace@example.com']);
        $this->repository->insert(['name' => 'Lise', 'email' => 'lise@example.com']);

        $rows = $this->repository->findAll([], 2, 1);

        self::assertCount(2, $rows);
        self::assertSame('Grace', $rows[0]['name']);
    }

    public function test_update_bulk_updates_matching_rows(): void
    {
        $this->repository->insert(['name' => 'Ada', 'email' => 'ada@example.com']);
        $this->repository->insert(['name' => 'Grace', 'email' => 'grace@example.com']);

        $affected = $this->repository->updateBulk(['name' => 'Ada'], ['email' => 'new@example.com']);

        self::assertSame(1, $affected);

        $rows = $this->repository->findAll(['name' => 'Ada']);
        self::assertSame('new@example.com', $rows[0]['email']);
    }

    public function test_delete_bulk_deletes_matching_rows(): void
    {
        $this->repository->insert(['name' => 'Ada', 'email' => 'ada@example.com']);
        $this->repository->insert(['name' => 'Grace', 'email' => 'grace@example.com']);

        $affected = $this->repository->deleteBulk(['name' => 'Ada']);

        self::assertSame(1, $affected);
        self::assertCount(1, $this->repository->findAll());
    }

    public function test_delete_bulk_with_no_criteria_deletes_all_rows(): void
    {
        $this->repository->insert(['name' => 'Ada', 'email' => 'ada@example.com']);
        $this->repository->insert(['name' => 'Grace', 'email' => 'grace@example.com']);

        $affected = $this->repository->deleteBulk([]);

        self::assertSame(2, $affected);
        self::assertCount(0, $this->repository->findAll());
    }
}
