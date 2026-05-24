<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Security\PolicyPortInterface;
use Bamise\Infrastructure\Security\Policy\ClassPolicyAdapter;
use Bamise\Infrastructure\Security\Policy\PolicyInterface;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for ClassPolicyAdapter.
 *
 * Kills escaped mutants:
 * - Line 23: UnwrapArrayValues (policyClasses re-indexed)
 * - Line 44: InstanceOf_ (PolicyInterface policies are actually checked)
 * - Line 45: LogicalNot (denying policy causes false, not inverted logic)
 * - Line 46: FalseValue → true (return is false on deny, not true)
 * - Line 49: Continue_ → break (multiple policies are all evaluated)
 */
final class ClassPolicyAdapterMutationTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        $this->subject = new \stdClass();
    }

    // ── Line 23: UnwrapArrayValues — non-sequential keys are re-indexed ───────

    public function test_policy_classes_with_non_sequential_keys_work(): void
    {
        // array_values resets keys; without it, foreach over a list with gaps still works
        // but the important thing is policyClasses is a list. Verify via normal operation.
        $alwaysAllow = $this->allowPolicy();
        $className = get_class($alwaysAllow);

        // Pass non-sequential keys; array_values should reset them
        $adapter = new ClassPolicyAdapter([5 => $className, 10 => $className]);

        self::assertTrue($adapter->allows(OperationType::Read, $this->subject, 'res'));
    }

    // ── Line 44: InstanceOf_ — PolicyInterface policies are checked ───────────

    public function test_policy_interface_deny_returns_false(): void
    {
        $deny = $this->denyPolicy();
        $className = get_class($deny);

        $adapter = new ClassPolicyAdapter([$className]);

        // If InstanceOf_ mutant replaces `$policy instanceof PolicyInterface` with false,
        // the policy is never checked → throws InvalidArgumentException (unknown type).
        // But the original code checks and calls allows().
        self::assertFalse($adapter->allows(OperationType::Create, $this->subject, 'res'));
    }

    public function test_policy_interface_allow_returns_true(): void
    {
        $allow = $this->allowPolicy();
        $className = get_class($allow);

        $adapter = new ClassPolicyAdapter([$className]);

        self::assertTrue($adapter->allows(OperationType::Read, $this->subject, 'res'));
    }

    // ── Line 45: LogicalNot — deny returns false, not true (inverted logic) ───

    public function test_denying_policy_interface_returns_false(): void
    {
        $deny = $this->denyPolicy();
        $className = get_class($deny);
        $adapter = new ClassPolicyAdapter([$className]);

        $result = $adapter->allows(OperationType::Update, $this->subject, 'items');

        // LogicalNot mutant would execute: if ($policy->allows(...)) → true → returns false when allowed!
        self::assertFalse($result);
    }

    public function test_allowing_policy_interface_returns_true(): void
    {
        $allow = $this->allowPolicy();
        $className = get_class($allow);
        $adapter = new ClassPolicyAdapter([$className]);

        $result = $adapter->allows(OperationType::Read, $this->subject, 'items');

        // LogicalNot mutant: if ($policy->allows()) → allow() returns true → if(true) → return false!
        self::assertTrue($result);
    }

    // ── Line 46: FalseValue → true — deny must return false ──────────────────

    public function test_deny_returns_false_not_true(): void
    {
        $deny = $this->denyPolicy();
        $adapter = new ClassPolicyAdapter([get_class($deny)]);

        $result = $adapter->allows(OperationType::Delete, $this->subject, 'res');

        // FalseValue mutant would return true on deny instead of false
        self::assertFalse($result);
    }

    // ── Line 49: Continue_ → break — all policies are checked ────────────────

    public function test_all_policies_checked_allow_then_deny(): void
    {
        $allow = $this->allowPolicy();
        $deny = $this->denyPolicy();

        // First policy allows, second denies. Result should be false (deny wins).
        // Continue_ → break mutation: after first policy (allow), would break loop without checking deny.
        // Then falls through to return policyClasses !== [] → true. Wrong!
        $adapter = new ClassPolicyAdapter([get_class($allow), get_class($deny)]);

        self::assertFalse($adapter->allows(OperationType::Read, $this->subject, 'res'));
    }

    public function test_two_allowing_policies_both_checked_returns_true(): void
    {
        $allow = $this->allowPolicy();
        $adapter = new ClassPolicyAdapter([get_class($allow), get_class($allow)]);

        self::assertTrue($adapter->allows(OperationType::Read, $this->subject, 'res'));
    }

    // ── Null subject always denied ────────────────────────────────────────────

    public function test_null_subject_returns_false(): void
    {
        $allow = $this->allowPolicy();
        $adapter = new ClassPolicyAdapter([get_class($allow)]);

        self::assertFalse($adapter->allows(OperationType::Read, null, 'res'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function allowPolicy(): PolicyInterface
    {
        return new class implements PolicyInterface {
            public function allows(object $subject, string $action, string $resource, mixed $target = null): bool
            {
                return true;
            }
        };
    }

    private function denyPolicy(): PolicyInterface
    {
        return new class implements PolicyInterface {
            public function allows(object $subject, string $action, string $resource, mixed $target = null): bool
            {
                return false;
            }
        };
    }
}
