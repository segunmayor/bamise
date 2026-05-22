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
}
