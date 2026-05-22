<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Context;

use Bamise\Application\Context\AuthSubjectDto;
use Bamise\Application\Context\SubjectFactory;
use Bamise\Domain\Model\Subject;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SubjectFactoryTest extends TestCase
{
    private SubjectFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new SubjectFactory();
    }

    public function test_returns_null_for_null_input(): void
    {
        self::assertNull($this->factory->fromAuthSubject(null));
    }

    public function test_passes_through_subject_instance(): void
    {
        $subject = new Subject(1, ['admin']);

        self::assertSame($subject, $this->factory->fromAuthSubject($subject));
    }

    public function test_maps_auth_subject_dto_to_domain_subject(): void
    {
        $dto = new AuthSubjectDto(42, ['editor'], ['posts.create']);

        $subject = $this->factory->fromAuthSubject($dto);

        self::assertInstanceOf(Subject::class, $subject);
        self::assertSame(42, $subject->id);
        self::assertSame(['editor'], $subject->roles);
        self::assertSame(['posts.create'], $subject->permissions);
    }

    public function test_maps_generic_object_with_id_method(): void
    {
        $external = new class {
            public function id(): int { return 7; }
            public function roles(): array { return ['viewer']; }
            public function permissions(): array { return ['read']; }
        };

        $subject = $this->factory->fromAuthSubject($external);

        self::assertInstanceOf(Subject::class, $subject);
        self::assertSame(7, $subject->id);
        self::assertSame(['viewer'], $subject->roles);
    }

    public function test_maps_generic_object_without_roles_or_permissions(): void
    {
        $external = new class {
            public function id(): string { return 'user-abc'; }
        };

        $subject = $this->factory->fromAuthSubject($external);

        self::assertInstanceOf(Subject::class, $subject);
        self::assertSame('user-abc', $subject->id);
        self::assertSame([], $subject->roles);
        self::assertSame([], $subject->permissions);
    }

    public function test_throws_for_unrecognised_object(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->factory->fromAuthSubject(new \stdClass());
    }

    public function test_throws_when_id_method_returns_invalid_type(): void
    {
        $external = new class {
            public function id(): array { return []; }
        };

        $this->expectException(InvalidArgumentException::class);

        $this->factory->fromAuthSubject($external);
    }
}
