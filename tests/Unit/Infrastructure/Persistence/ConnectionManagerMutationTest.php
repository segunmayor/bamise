<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Persistence;

use Bamise\Contract\Persistence\ConnectionInterface;
use Bamise\Infrastructure\Persistence\PDO\ConnectionManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for ConnectionManager.
 *
 * Kills escaped PublicVisibility mutants by calling each method from test context:
 * - Line 17: register() PublicVisibility
 * - Line 22: get() PublicVisibility
 * - Line 24: get() AssignCoalesce ($name ??= $this->defaultName)
 * - Line 26: LogicalNot on isset check
 * - Line 27: Throw_
 * - Line 35: setDefault() PublicVisibility
 * - Line 37: LogicalNot + Throw_
 * - Line 46: defaultName() PublicVisibility
 * - Line 54: all() PublicVisibility
 */
final class ConnectionManagerMutationTest extends TestCase
{
    private ConnectionManager $manager;
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        $this->manager = new ConnectionManager();
        $this->connection = $this->createMock(ConnectionInterface::class);
    }

    // ── register() is callable ────────────────────────────────────────────────

    public function test_register_stores_connection(): void
    {
        $this->manager->register('primary', $this->connection);

        self::assertSame($this->connection, $this->manager->get('primary'));
    }

    // ── get() without name uses default ───────────────────────────────────────

    public function test_get_without_name_uses_default(): void
    {
        $this->manager->register('default', $this->connection);

        self::assertSame($this->connection, $this->manager->get());
    }

    // ── Line 24: AssignCoalesce ($name ??= defaultName) ──────────────────────

    public function test_get_with_null_name_uses_default(): void
    {
        $this->manager->register('default', $this->connection);

        self::assertSame($this->connection, $this->manager->get(null));
    }

    // ── Line 26: LogicalNot on isset check ───────────────────────────────────

    public function test_get_registered_connection_returns_it(): void
    {
        $conn2 = $this->createMock(ConnectionInterface::class);
        $this->manager->register('replica', $conn2);

        self::assertSame($conn2, $this->manager->get('replica'));
    }

    // ── Line 27: Throw_ for unregistered connection ───────────────────────────

    public function test_get_unregistered_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not registered/');

        $this->manager->get('nonexistent');
    }

    // ── setDefault() is callable ──────────────────────────────────────────────

    public function test_set_default_changes_default_name(): void
    {
        $conn2 = $this->createMock(ConnectionInterface::class);
        $this->manager->register('default', $this->connection);
        $this->manager->register('replica', $conn2);

        $this->manager->setDefault('replica');

        self::assertSame($conn2, $this->manager->get());
    }

    // ── Line 37: LogicalNot + Throw_ in setDefault ───────────────────────────

    public function test_set_default_to_unregistered_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not registered/');

        $this->manager->setDefault('ghost');
    }

    public function test_set_default_to_registered_succeeds(): void
    {
        $this->manager->register('db1', $this->connection);

        $this->manager->setDefault('db1');

        self::assertSame('db1', $this->manager->defaultName());
    }

    // ── defaultName() returns current default ─────────────────────────────────

    public function test_default_name_is_initially_default(): void
    {
        self::assertSame('default', $this->manager->defaultName());
    }

    public function test_default_name_reflects_set_default_call(): void
    {
        $this->manager->register('secondary', $this->connection);
        $this->manager->setDefault('secondary');

        self::assertSame('secondary', $this->manager->defaultName());
    }

    // ── all() returns all connections ─────────────────────────────────────────

    public function test_all_returns_empty_initially(): void
    {
        self::assertSame([], $this->manager->all());
    }

    public function test_all_returns_registered_connections(): void
    {
        $conn2 = $this->createMock(ConnectionInterface::class);
        $this->manager->register('a', $this->connection);
        $this->manager->register('b', $conn2);

        $all = $this->manager->all();

        self::assertCount(2, $all);
        self::assertSame($this->connection, $all['a']);
        self::assertSame($conn2, $all['b']);
    }
}
