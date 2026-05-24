<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Contract\AuditLoggerPortInterface;
use Bamise\Contract\AuthPortInterface;
use Bamise\Contract\Security\CsrfPortInterface;
use Bamise\Contract\Security\PolicyPortInterface;
use Bamise\Contract\Security\RateLimiterPortInterface;
use Bamise\Contract\Security\RequestSignerPortInterface;
use Bamise\Contract\Security\SanitizerPortInterface;
use Bamise\Infrastructure\Cache\InMemoryCache;
use Bamise\Infrastructure\Security\Auth\BearerTokenAuthAdapter;
use Bamise\Infrastructure\Security\Auth\JwtAuthAdapter;
use Bamise\Infrastructure\Security\Auth\SessionAuthAdapter;
use Bamise\Infrastructure\Security\SecurityFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SecurityFactoryTest extends TestCase
{
    private SecurityFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new SecurityFactory(
            new InMemoryCache(),
            new NullLogger(),
        );
    }

    public function test_csrf_returns_csrf_port(): void
    {
        self::assertInstanceOf(CsrfPortInterface::class, $this->factory->csrf());
    }

    public function test_sanitizer_returns_sanitizer_port(): void
    {
        self::assertInstanceOf(SanitizerPortInterface::class, $this->factory->sanitizer());
    }

    public function test_rate_limiter_returns_rate_limiter_port(): void
    {
        self::assertInstanceOf(RateLimiterPortInterface::class, $this->factory->rateLimiter());
    }

    public function test_request_signer_returns_signer_port(): void
    {
        self::assertInstanceOf(RequestSignerPortInterface::class, $this->factory->requestSigner());
    }

    public function test_audit_logger_returns_audit_logger_port(): void
    {
        self::assertInstanceOf(AuditLoggerPortInterface::class, $this->factory->auditLogger());
    }

    public function test_bearer_auth_returns_auth_port(): void
    {
        $auth = $this->factory->bearerAuth();

        self::assertInstanceOf(AuthPortInterface::class, $auth);
        self::assertInstanceOf(BearerTokenAuthAdapter::class, $auth);
    }

    public function test_session_auth_returns_auth_port(): void
    {
        $auth = $this->factory->sessionAuth();

        self::assertInstanceOf(AuthPortInterface::class, $auth);
        self::assertInstanceOf(SessionAuthAdapter::class, $auth);
    }

    public function test_jwt_auth_returns_jwt_adapter(): void
    {
        $auth = $this->factory->jwtAuth('supersecret');

        self::assertInstanceOf(AuthPortInterface::class, $auth);
        self::assertInstanceOf(JwtAuthAdapter::class, $auth);
    }

    public function test_policy_returns_policy_chain(): void
    {
        $p1 = $this->createStub(PolicyPortInterface::class);
        $p2 = $this->createStub(PolicyPortInterface::class);

        $chain = $this->factory->policy($p1, $p2);

        self::assertInstanceOf(PolicyPortInterface::class, $chain);
    }

    public function test_policy_with_no_args_returns_policy_port(): void
    {
        $chain = $this->factory->policy();

        self::assertInstanceOf(PolicyPortInterface::class, $chain);
    }

    public function test_each_call_returns_new_instance(): void
    {
        $a = $this->factory->csrf();
        $b = $this->factory->csrf();

        self::assertNotSame($a, $b);
    }
}
