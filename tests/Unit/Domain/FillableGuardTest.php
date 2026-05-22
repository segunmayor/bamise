<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Domain;

use Bamise\Domain\Exception\MassAssignmentException;
use Bamise\Domain\Service\FillableGuard;
use PHPUnit\Framework\TestCase;

final class FillableGuardTest extends TestCase
{
    private FillableGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new FillableGuard();
    }

    public function test_filters_to_fillable_and_strips_guarded(): void
    {
        $data = ['name' => 'Ada', 'email' => 'a@b.c', 'is_admin' => true];
        $result = $this->guard->filter($data, ['name', 'email'], ['is_admin']);

        self::assertSame(['name' => 'Ada', 'email' => 'a@b.c'], $result);
    }

    public function test_allows_all_non_guarded_when_fillable_empty(): void
    {
        $data = ['name' => 'Ada', 'title' => 'Eng'];
        $result = $this->guard->filter($data, [], ['id']);

        self::assertSame($data, $result);
    }

    public function test_throws_on_mass_assignment_violation(): void
    {
        $this->expectException(MassAssignmentException::class);
        $this->guard->filter(['secret' => 'x'], ['name'], []);
    }

    public function test_guarded_fields_are_silently_stripped(): void
    {
        $result = $this->guard->filter(['is_admin' => true, 'name' => 'Ada'], ['name'], ['is_admin']);

        self::assertSame(['name' => 'Ada'], $result);
    }
}
