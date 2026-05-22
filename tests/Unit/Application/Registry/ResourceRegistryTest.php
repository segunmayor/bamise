<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Registry;

use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Tests\Fixtures\FakeResourceDefinition;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ResourceRegistryTest extends TestCase
{
    public function test_get_returns_registered_definition(): void
    {
        $registry = new ResourceRegistry();
        $def = new FakeResourceDefinition();
        $registry->register('users', $def);

        self::assertSame($def, $registry->get('users'));
    }

    public function test_get_throws_for_unknown_resource(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ResourceRegistry())->get('missing');
    }

    public function test_has_returns_true_for_registered_resource(): void
    {
        $registry = new ResourceRegistry();
        $registry->register('users', new FakeResourceDefinition());

        self::assertTrue($registry->has('users'));
    }

    public function test_has_returns_false_for_unregistered_resource(): void
    {
        self::assertFalse((new ResourceRegistry())->has('users'));
    }

    public function test_all_returns_all_registered_definitions(): void
    {
        $a = new FakeResourceDefinition();
        $b = new FakeResourceDefinition();
        $registry = new ResourceRegistry(['users' => $a, 'posts' => $b]);

        self::assertSame(['users' => $a, 'posts' => $b], $registry->all());
    }

    public function test_register_overwrites_existing_entry(): void
    {
        $registry = new ResourceRegistry();
        $registry->register('users', new FakeResourceDefinition());
        $new = new FakeResourceDefinition(table: 'accounts');
        $registry->register('users', $new);

        self::assertSame($new, $registry->get('users'));
    }

    public function test_constructor_iterable_registers_resources(): void
    {
        $def = new FakeResourceDefinition();
        $registry = new ResourceRegistry(['orders' => $def]);

        self::assertTrue($registry->has('orders'));
        self::assertSame($def, $registry->get('orders'));
    }
}
