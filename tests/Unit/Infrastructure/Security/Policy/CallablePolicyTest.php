<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security\Policy;

use Bamise\Contract\Enum\OperationType;
use Bamise\Domain\Model\Subject;
use Bamise\Infrastructure\Security\Policy\CallablePolicy;
use PHPUnit\Framework\TestCase;

final class CallablePolicyTest extends TestCase
{
    public function test_allows_when_predicate_returns_true(): void
    {
        $policy = new CallablePolicy(static fn (): bool => true);

        self::assertTrue($policy->allows(OperationType::Create, new Subject(1), 'users'));
    }

    public function test_denies_when_predicate_returns_false(): void
    {
        $policy = new CallablePolicy(static fn (): bool => false);

        self::assertFalse($policy->allows(OperationType::Create, new Subject(1), 'users'));
    }

    public function test_predicate_receives_correct_arguments(): void
    {
        $captured = [];
        $policy = new CallablePolicy(
            static function (OperationType $op, ?object $sub, string $res) use (&$captured): bool {
                $captured = [$op, $sub, $res];
                return true;
            },
        );
        $subject = new Subject(7);

        $policy->allows(OperationType::Delete, $subject, 'posts');

        self::assertSame(OperationType::Delete, $captured[0]);
        self::assertSame($subject, $captured[1]);
        self::assertSame('posts', $captured[2]);
    }

    public function test_works_with_null_subject(): void
    {
        $policy = new CallablePolicy(
            static fn (OperationType $op, ?object $sub, string $res): bool => $sub === null,
        );

        self::assertTrue($policy->allows(OperationType::Read, null, 'users'));
    }
}
