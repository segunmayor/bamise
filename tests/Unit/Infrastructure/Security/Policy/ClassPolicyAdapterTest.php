<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security\Policy;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Security\PolicyPortInterface;
use Bamise\Domain\Model\Subject;
use Bamise\Infrastructure\Security\Policy\ClassPolicyAdapter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ClassPolicyAdapterTest extends TestCase
{
    public function test_returns_false_when_subject_is_null(): void
    {
        $adapter = new ClassPolicyAdapter([AllowAllPolicy::class]);

        self::assertFalse($adapter->allows(OperationType::Create, null, 'users'));
    }

    public function test_returns_false_when_no_policy_classes(): void
    {
        $adapter = new ClassPolicyAdapter([]);

        self::assertFalse($adapter->allows(OperationType::Create, new Subject(1), 'users'));
    }

    public function test_allows_when_all_policies_allow(): void
    {
        $adapter = new ClassPolicyAdapter([AllowAllPolicy::class, AllowAllPolicy::class]);

        self::assertTrue($adapter->allows(OperationType::Create, new Subject(1), 'users'));
    }

    public function test_denies_when_any_policy_denies(): void
    {
        $adapter = new ClassPolicyAdapter([AllowAllPolicy::class, DenyAllPolicy::class]);

        self::assertFalse($adapter->allows(OperationType::Create, new Subject(1), 'users'));
    }

    public function test_throws_for_nonexistent_class(): void
    {
        $adapter = new ClassPolicyAdapter(['Bamise\NonExistent\Policy']);

        $this->expectException(InvalidArgumentException::class);

        $adapter->allows(OperationType::Create, new Subject(1), 'users');
    }

    public function test_throws_for_class_that_does_not_implement_policy_interface(): void
    {
        $adapter = new ClassPolicyAdapter([NotAPolicyClass::class]);

        $this->expectException(InvalidArgumentException::class);

        $adapter->allows(OperationType::Create, new Subject(1), 'users');
    }
}

final class AllowAllPolicy implements PolicyPortInterface
{
    public function allows(OperationType $operation, ?object $subject, string $resource): bool
    {
        return true;
    }
}

final class DenyAllPolicy implements PolicyPortInterface
{
    public function allows(OperationType $operation, ?object $subject, string $resource): bool
    {
        return false;
    }
}

final class NotAPolicyClass
{
}
