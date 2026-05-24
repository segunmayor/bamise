<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Context;

use Bamise\Application\Context\AuthSubjectDto;
use Bamise\Application\Context\SubjectFactory;
use Bamise\Domain\Model\Subject;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for SubjectFactory.
 *
 * Kills escaped mutants:
 * - Lines 37/40: CastArray, UnwrapArrayMap, UnwrapArrayValues for roles and permissions
 */
final class SubjectFactoryMutationTest extends TestCase
{
    private SubjectFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new SubjectFactory();
    }

    // ── Lines 37/40: CastArray — roles/permissions must be coerced to array ──

    public function test_roles_from_array_returning_method_are_normalized(): void
    {
        $authSubject = new class {
            public function id(): int { return 1; }
            /** @return list<string> */
            public function roles(): array { return ['admin', 'editor']; }
        };

        $subject = $this->factory->fromAuthSubject($authSubject);

        self::assertNotNull($subject);
        self::assertSame(['admin', 'editor'], $subject->roles);
    }

    public function test_permissions_from_array_returning_method_are_normalized(): void
    {
        $authSubject = new class {
            public function id(): int { return 2; }
            /** @return list<string> */
            public function permissions(): array { return ['posts.read', 'posts.write']; }
        };

        $subject = $this->factory->fromAuthSubject($authSubject);

        self::assertNotNull($subject);
        self::assertSame(['posts.read', 'posts.write'], $subject->permissions);
    }

    public function test_roles_string_values_are_cast_to_string(): void
    {
        // CastArray mutation: removes (array) cast, which would fail if roles() returns a non-array
        $authSubject = new class {
            public function id(): string { return 'user-1'; }
            /** @return array<int, string> */
            public function roles(): array { return [0 => 'admin']; }
        };

        $subject = $this->factory->fromAuthSubject($authSubject);

        self::assertNotNull($subject);
        // array_values re-indexes so key is 0
        self::assertSame(['admin'], $subject->roles);
    }

    public function test_roles_with_integer_values_cast_to_string(): void
    {
        // UnwrapArrayMap mutation: removes strval cast
        $authSubject = new class {
            public function id(): int { return 5; }

            /** @return array<int|string> */
            public function roles(): array { return [1, 2, 'admin']; } // @phpstan-ignore-line
        };

        $subject = $this->factory->fromAuthSubject($authSubject);

        self::assertNotNull($subject);
        // strval converts ints to strings
        self::assertSame(['1', '2', 'admin'], $subject->roles);
    }

    public function test_permissions_with_integer_values_cast_to_string(): void
    {
        $authSubject = new class {
            public function id(): int { return 5; }

            /** @return array<int|string> */
            public function permissions(): array { return [1, 'read']; } // @phpstan-ignore-line
        };

        $subject = $this->factory->fromAuthSubject($authSubject);

        self::assertNotNull($subject);
        self::assertSame(['1', 'read'], $subject->permissions);
    }

    public function test_roles_array_is_reindexed_with_array_values(): void
    {
        // UnwrapArrayValues mutation: removes array_values, leaving non-sequential keys
        $authSubject = new class {
            public function id(): int { return 5; }

            /** @return array<int, string> */
            public function roles(): array { return [5 => 'admin', 10 => 'mod']; }
        };

        $subject = $this->factory->fromAuthSubject($authSubject);

        self::assertNotNull($subject);
        // array_values re-indexes to 0, 1
        self::assertSame(['admin', 'mod'], $subject->roles);
        self::assertArrayHasKey(0, $subject->roles);
    }

    public function test_permissions_array_is_reindexed(): void
    {
        $authSubject = new class {
            public function id(): int { return 5; }

            /** @return array<int, string> */
            public function permissions(): array { return [3 => 'read', 7 => 'write']; }
        };

        $subject = $this->factory->fromAuthSubject($authSubject);

        self::assertNotNull($subject);
        self::assertSame(['read', 'write'], $subject->permissions);
    }

    // ── Null / Subject passthrough ────────────────────────────────────────────

    public function test_null_returns_null(): void
    {
        self::assertNull($this->factory->fromAuthSubject(null));
    }

    public function test_subject_instance_returned_directly(): void
    {
        $subject = new Subject('u1', ['admin'], []);

        self::assertSame($subject, $this->factory->fromAuthSubject($subject));
    }

    public function test_auth_subject_dto_mapped(): void
    {
        $dto = new AuthSubjectDto(42, ['editor'], ['read']);

        $subject = $this->factory->fromAuthSubject($dto);

        self::assertNotNull($subject);
        self::assertSame(42, $subject->id);
        self::assertSame(['editor'], $subject->roles);
    }

    // ── id() must return string|int ───────────────────────────────────────────

    public function test_invalid_id_type_throws(): void
    {
        $authSubject = new class {
            public function id(): array { return []; } // @phpstan-ignore-line
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/string or int/');

        $this->factory->fromAuthSubject($authSubject);
    }

    public function test_no_id_method_throws_unmappable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Cannot map auth subject/');

        $this->factory->fromAuthSubject(new \stdClass());
    }

    public function test_no_roles_method_defaults_to_empty_array(): void
    {
        $authSubject = new class {
            public function id(): string { return 'x'; }
        };

        $subject = $this->factory->fromAuthSubject($authSubject);

        self::assertNotNull($subject);
        self::assertSame([], $subject->roles);
    }

    public function test_no_permissions_method_defaults_to_empty_array(): void
    {
        $authSubject = new class {
            public function id(): string { return 'x'; }
        };

        $subject = $this->factory->fromAuthSubject($authSubject);

        self::assertNotNull($subject);
        self::assertSame([], $subject->permissions);
    }
}
