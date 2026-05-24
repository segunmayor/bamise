<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Persistence\Repository;

use Bamise\Contract\Persistence\RepositoryInterface;
use Bamise\Infrastructure\Persistence\Repository\PdoRepository;
use Bamise\Infrastructure\Persistence\Repository\PdoRepositoryFactory;
use Bamise\Infrastructure\Persistence\Repository\ResourceMetadata;
use Bamise\Tests\Fixtures\SqliteTestConnection;
use Bamise\Tests\Fixtures\TestUserResourceDefinition;
use PHPUnit\Framework\TestCase;

final class PdoRepositoryFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        if (! SqliteTestConnection::isAvailable()) {
            $this->markTestSkipped('pdo_sqlite not available.');
        }
    }

    public function test_for_creates_pdo_repository_from_definition(): void
    {
        $factory = new PdoRepositoryFactory(SqliteTestConnection::create());
        $repo = $factory->for(new TestUserResourceDefinition());

        self::assertInstanceOf(RepositoryInterface::class, $repo);
        self::assertInstanceOf(PdoRepository::class, $repo);
    }

    public function test_for_metadata_creates_pdo_repository(): void
    {
        $factory = new PdoRepositoryFactory(SqliteTestConnection::create());
        $meta = new ResourceMetadata('products', 'product_id', ['name', 'price']);
        $repo = $factory->forMetadata($meta);

        self::assertInstanceOf(PdoRepository::class, $repo);
    }

    public function test_each_call_to_for_returns_a_new_instance(): void
    {
        $factory = new PdoRepositoryFactory(SqliteTestConnection::create());
        $definition = new TestUserResourceDefinition();

        $repoA = $factory->for($definition);
        $repoB = $factory->for($definition);

        self::assertNotSame($repoA, $repoB);
    }
}
