<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Domain;

use Bamise\Domain\Model\Permission;
use Bamise\Domain\Model\Subject;
use Bamise\Domain\Service\PermissionEvaluator;
use PHPUnit\Framework\TestCase;

final class PermissionEvaluatorTest extends TestCase
{
    private PermissionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new PermissionEvaluator();
    }

    public function test_granted_when_permission_present(): void
    {
        $subject = new Subject(1, ['admin'], ['users.create', 'users.read']);
        $permission = Permission::fromString('users.create');

        self::assertTrue($this->evaluator->isGranted($subject, $permission));
    }

    public function test_denied_when_permission_missing(): void
    {
        $subject = new Subject(1, ['viewer'], ['users.read']);
        $permission = Permission::fromString('users.delete');

        self::assertFalse($this->evaluator->isGranted($subject, $permission));
    }

    public function test_target_record_is_ignored_for_string_permissions(): void
    {
        $subject = new Subject(1, [], ['posts.update']);
        $permission = Permission::fromString('posts.update');

        self::assertTrue($this->evaluator->isGranted($subject, $permission, ['author_id' => 99]));
    }
}
