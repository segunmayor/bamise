<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Domain\Model;

use Bamise\Domain\Model\FieldBag;
use Bamise\Domain\Model\Permission;
use Bamise\Domain\Model\Resource;
use Bamise\Domain\Model\Subject;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DomainModelTest extends TestCase
{
    // ── FieldBag ───────────────────────────────────────────────────────────────

    public function test_fieldbag_all_returns_all_fields(): void
    {
        $bag = new FieldBag(['name' => 'Alice', 'age' => 30]);

        self::assertSame(['name' => 'Alice', 'age' => 30], $bag->all());
    }

    public function test_fieldbag_get_returns_value_for_key(): void
    {
        $bag = new FieldBag(['key' => 'val']);

        self::assertSame('val', $bag->get('key'));
    }

    public function test_fieldbag_get_returns_default_for_missing_key(): void
    {
        $bag = new FieldBag([]);

        self::assertNull($bag->get('missing'));
        self::assertSame('fallback', $bag->get('missing', 'fallback'));
    }

    public function test_fieldbag_has_returns_true_for_existing_key(): void
    {
        $bag = new FieldBag(['x' => null]);

        self::assertTrue($bag->has('x'));
    }

    public function test_fieldbag_has_returns_false_for_missing_key(): void
    {
        $bag = new FieldBag([]);

        self::assertFalse($bag->has('x'));
    }

    public function test_fieldbag_count_reflects_number_of_fields(): void
    {
        $bag = new FieldBag(['a' => 1, 'b' => 2, 'c' => 3]);

        self::assertSame(3, $bag->count());
    }

    public function test_fieldbag_empty(): void
    {
        $bag = new FieldBag([]);

        self::assertSame([], $bag->all());
        self::assertSame(0, $bag->count());
    }

    public function test_fieldbag_has_returns_true_for_null_value(): void
    {
        $bag = new FieldBag(['nullable' => null]);

        self::assertTrue($bag->has('nullable'));
        self::assertNull($bag->get('nullable'));
    }

    // ── Permission ─────────────────────────────────────────────────────────────

    public function test_permission_constructor_stores_resource_and_action(): void
    {
        $p = new Permission('users', 'create');

        self::assertSame('users', $p->resource);
        self::assertSame('create', $p->action);
    }

    public function test_permission_from_string(): void
    {
        $p = Permission::fromString('posts.delete');

        self::assertSame('posts', $p->resource);
        self::assertSame('delete', $p->action);
    }

    public function test_permission_to_string(): void
    {
        $p = new Permission('orders', 'read');

        self::assertSame('orders.read', $p->toString());
    }

    public function test_permission_from_string_roundtrips(): void
    {
        $original = 'items.update';
        $p = Permission::fromString($original);

        self::assertSame($original, $p->toString());
    }

    public function test_permission_from_string_rejects_missing_dot(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Permission::fromString('nodot');
    }

    public function test_permission_from_string_rejects_empty_resource(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Permission::fromString('.action');
    }

    public function test_permission_from_string_rejects_empty_action(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Permission::fromString('resource.');
    }

    public function test_permission_from_string_rejects_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Permission::fromString('');
    }

    public function test_permission_from_string_with_multiple_dots_uses_second_segment(): void
    {
        // explode(..., 2) means "users.create.extra" → resource=users, action=create.extra
        $p = Permission::fromString('users.create.extra');

        self::assertSame('users', $p->resource);
        self::assertSame('create.extra', $p->action);
    }

    // ── Resource ───────────────────────────────────────────────────────────────

    public function test_resource_constructor_stores_properties(): void
    {
        $r = new Resource('users', 'user_table', 'user_id');

        self::assertSame('users', $r->name);
        self::assertSame('user_table', $r->table);
        self::assertSame('user_id', $r->primaryKey);
    }

    public function test_resource_from_definition_factory(): void
    {
        $r = Resource::fromDefinition('posts', 'posts_table', 'post_id');

        self::assertSame('posts', $r->name);
        self::assertSame('posts_table', $r->table);
        self::assertSame('post_id', $r->primaryKey);
    }

    // ── Subject ────────────────────────────────────────────────────────────────

    public function test_subject_stores_id_and_roles(): void
    {
        $s = new Subject(42, ['admin', 'editor']);

        self::assertSame(42, $s->id);
        self::assertSame(['admin', 'editor'], $s->roles);
    }

    public function test_subject_default_roles_and_permissions_are_empty(): void
    {
        $s = new Subject('user-uuid');

        self::assertSame([], $s->roles);
        self::assertSame([], $s->permissions);
    }

    public function test_subject_has_permission_returns_true_when_granted(): void
    {
        $s = new Subject(1, [], ['users.create', 'users.read']);

        self::assertTrue($s->hasPermission('users.create'));
        self::assertTrue($s->hasPermission('users.read'));
    }

    public function test_subject_has_permission_returns_false_when_not_granted(): void
    {
        $s = new Subject(1, [], ['users.read']);

        self::assertFalse($s->hasPermission('users.delete'));
    }

    public function test_subject_has_permission_false_for_empty_permissions(): void
    {
        $s = new Subject(1);

        self::assertFalse($s->hasPermission('anything.read'));
    }

    public function test_subject_with_string_id(): void
    {
        $s = new Subject('uuid-abc');

        self::assertSame('uuid-abc', $s->id);
    }
}
