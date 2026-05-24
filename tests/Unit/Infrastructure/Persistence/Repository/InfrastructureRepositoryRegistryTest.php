<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Persistence\Repository;

use Bamise\Contract\Persistence\RepositoryInterface;
use Bamise\Infrastructure\Persistence\Repository\InfrastructureRepositoryRegistry;
use Bamise\Infrastructure\Persistence\Repository\PdoRepositoryFactory;
use Bamise\Tests\Fixtures\FakeRepository;
use Bamise\Tests\Fixtures\SqliteTestConnection;
use Bamise\Tests\Fixtures\TestUserResourceDefinition;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InfrastructureRepositoryRegistryTest extends TestCase
{
    private InfrastructureRepositoryRegistry $registry;

    protected function setUp(): void
    {
        if (! SqliteTestConnection::isAvailable()) {
            $this->markTestSkipped('pdo_sqlite not available.');
        }

        $connection = SqliteTestConnection::create();
        $factory = new PdoRepositoryFactory($connection);
        $this->registry = new InfrastructureRepositoryRegistry($factory);
    }

    public function test_register_and_get_by_resource_name(): void
    {
        $this->registry->register('users', new TestUserResourceDefinition());

        $repo = $this->registry->get('users');

        self::assertInstanceOf(RepositoryInterface::class, $repo);
    }

    public function test_register_repository_directly(): void
    {
        $fake = new FakeRepository();
        $this->registry->registerRepository('orders', $fake);

        self::assertSame($fake, $this->registry->get('orders'));
    }

    public function test_has_returns_true_after_registration(): void
    {
        $this->registry->registerRepository('items', new FakeRepository());

        self::assertTrue($this->registry->has('items'));
    }

    public function test_has_returns_false_for_unknown_resource(): void
    {
        self::assertFalse($this->registry->has('nonexistent'));
    }

    public function test_get_throws_for_unregistered_resource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No repository registered for resource "missing".');

        $this->registry->get('missing');
    }

    public function test_all_returns_all_registered_repositories(): void
    {
        $fakeA = new FakeRepository();
        $fakeB = new FakeRepository();
        $this->registry->registerRepository('a', $fakeA);
        $this->registry->registerRepository('b', $fakeB);

        $all = $this->registry->all();

        self::assertArrayHasKey('a', $all);
        self::assertArrayHasKey('b', $all);
        self::assertCount(2, $all);
    }

    public function test_register_overwrites_previous_registration(): void
    {
        $first = new FakeRepository();
        $second = new FakeRepository();

        $this->registry->registerRepository('r', $first);
        $this->registry->registerRepository('r', $second);

        self::assertSame($second, $this->registry->get('r'));
    }
}
