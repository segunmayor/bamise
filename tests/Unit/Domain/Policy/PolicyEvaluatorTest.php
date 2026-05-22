<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Domain\Policy;

use Bamise\Domain\Model\Subject;
use Bamise\Domain\Policy\PolicyEvaluator;
use Bamise\Domain\Service\OperationTypeMapper;
use Bamise\Tests\Fixtures\FakePolicyPort;
use PHPUnit\Framework\TestCase;

final class PolicyEvaluatorTest extends TestCase
{
    public function test_returns_true_when_policy_allows(): void
    {
        $evaluator = new PolicyEvaluator(new FakePolicyPort(allowed: true), new OperationTypeMapper());
        $subject = new Subject(1, ['admin']);

        self::assertTrue($evaluator->evaluate($subject, 'create', 'users'));
    }

    public function test_returns_false_when_policy_denies(): void
    {
        $evaluator = new PolicyEvaluator(new FakePolicyPort(allowed: false), new OperationTypeMapper());
        $subject = new Subject(1, []);

        self::assertFalse($evaluator->evaluate($subject, 'create', 'users'));
    }

    public function test_returns_false_for_unknown_action(): void
    {
        $evaluator = new PolicyEvaluator(new FakePolicyPort(allowed: true), new OperationTypeMapper());
        $subject = new Subject(1, []);

        self::assertFalse($evaluator->evaluate($subject, 'unknown_action', 'users'));
    }

    public function test_action_is_case_insensitive(): void
    {
        $evaluator = new PolicyEvaluator(new FakePolicyPort(allowed: true), new OperationTypeMapper());
        $subject = new Subject(1, []);

        self::assertTrue($evaluator->evaluate($subject, 'CREATE', 'users'));
        self::assertTrue($evaluator->evaluate($subject, 'Delete', 'posts'));
    }
}
