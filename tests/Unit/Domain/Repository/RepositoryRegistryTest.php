<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Domain\Repository;

use Bamise\Domain\Repository\RepositoryRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RepositoryRegistryTest extends TestCase
{
    public function test_resolve_returns_registered_key(): void
    {
        $registry = new RepositoryRegistry();
        $registry->register('users', 'UserRepository');

        self::assertSame('UserRepository', $registry->resolve('users'));
    }

    public function test_resolve_throws_for_unknown_resource(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RepositoryRegistry())->resolve('missing');
    }

    public function test_has_returns_true_for_registered(): void
    {
        $registry = new RepositoryRegistry();
        $registry->register('users', 'UserRepository');

        self::assertTrue($registry->has('users'));
    }

    public function test_has_returns_false_for_unregistered(): void
    {
        self::assertFalse((new RepositoryRegistry())->has('users'));
    }

    public function test_all_returns_full_map(): void
    {
        $registry = new RepositoryRegistry();
        $registry->register('users', 'UserRepo');
        $registry->register('posts', 'PostRepo');

        self::assertSame(['users' => 'UserRepo', 'posts' => 'PostRepo'], $registry->all());
    }

    public function test_register_overwrites_existing_entry(): void
    {
        $registry = new RepositoryRegistry();
        $registry->register('users', 'OldRepo');
        $registry->register('users', 'NewRepo');

        self::assertSame('NewRepo', $registry->resolve('users'));
    }
}
