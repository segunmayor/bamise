<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Contract\Enum\OperationType;
use Bamise\Domain\Model\Subject;
use Bamise\Infrastructure\Security\Policy\CallablePolicy;
use Bamise\Infrastructure\Security\Policy\PolicyChain;
use Bamise\Tests\Fixtures\FakePolicyPort;
use PHPUnit\Framework\TestCase;

final class PolicyChainTest extends TestCase
{
    public function test_all_policies_must_allow(): void
    {
        $chain = new PolicyChain(
            new FakePolicyPort(true),
            new CallablePolicy(
                static fn (OperationType $op, ?object $subject, string $resource): bool => $resource === 'users',
            ),
        );

        self::assertTrue($chain->allows(OperationType::Read, new Subject(1), 'users'));
        self::assertFalse($chain->allows(OperationType::Read, new Subject(1), 'posts'));
    }

    public function test_empty_chain_denies(): void
    {
        $chain = new PolicyChain();

        self::assertFalse($chain->allows(OperationType::Read, new Subject(1), 'users'));
    }
}
