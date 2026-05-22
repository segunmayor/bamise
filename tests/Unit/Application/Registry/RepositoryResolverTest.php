<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Registry;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Tests\Fixtures\FakeRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RepositoryResolverTest extends TestCase
{
    public function test_for_returns_registered_repository(): void
    {
        $repo = new FakeRepository();
        $resolver = new RepositoryResolver(['users' => $repo]);

        self::assertSame($repo, $resolver->for('users'));
    }

    public function test_for_throws_for_unknown_resource(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RepositoryResolver())->for('missing');
    }

    public function test_has_returns_true_after_registration(): void
    {
        $resolver = new RepositoryResolver(['users' => new FakeRepository()]);

        self::assertTrue($resolver->has('users'));
    }

    public function test_has_returns_false_for_unregistered(): void
    {
        self::assertFalse((new RepositoryResolver())->has('users'));
    }

    public function test_register_adds_repository_after_construction(): void
    {
        $resolver = new RepositoryResolver();
        $repo = new FakeRepository();
        $resolver->register('orders', $repo);

        self::assertSame($repo, $resolver->for('orders'));
    }

    public function test_all_returns_all_repositories(): void
    {
        $a = new FakeRepository();
        $b = new FakeRepository();
        $resolver = new RepositoryResolver(['users' => $a, 'posts' => $b]);

        self::assertSame(['users' => $a, 'posts' => $b], $resolver->all());
    }
}
