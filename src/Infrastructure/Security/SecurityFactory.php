<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security;

use Bamise\Contract\AuditLoggerPortInterface;
use Bamise\Contract\AuthPortInterface;
use Bamise\Contract\CachePortInterface;
use Bamise\Contract\Security\CsrfPortInterface;
use Bamise\Contract\Security\PolicyPortInterface;
use Bamise\Contract\Security\RateLimiterPortInterface;
use Bamise\Contract\Security\RequestSignerPortInterface;
use Bamise\Contract\Security\SanitizerPortInterface;
use Bamise\Infrastructure\Security\Audit\AuditConfig;
use Bamise\Infrastructure\Security\Audit\PsrAuditLogger;
use Bamise\Infrastructure\Security\Auth\BearerTokenAuthAdapter;
use Bamise\Infrastructure\Security\Auth\JwtAuthAdapter;
use Bamise\Infrastructure\Security\Auth\SessionAuthAdapter;
use Bamise\Infrastructure\Security\Csrf\CsrfConfig;
use Bamise\Infrastructure\Security\Csrf\CsrfTokenGenerator;
use Bamise\Infrastructure\Security\Csrf\SessionCsrfService;
use Bamise\Infrastructure\Security\RateLimit\CacheRateLimiter;
use Bamise\Infrastructure\Security\RateLimit\RateLimitConfig;
use Bamise\Infrastructure\Security\Sanitizer\HtmlSanitizer;
use Bamise\Infrastructure\Security\Sanitizer\SanitizerConfig;
use Bamise\Infrastructure\Security\Signing\HmacRequestSigner;
use Bamise\Infrastructure\Security\Signing\SigningConfig;
use Psr\Log\LoggerInterface;

/**
 * Convenience factory for default security port implementations (not a service locator).
 */
final class SecurityFactory
{
    public function __construct(
        private readonly CachePortInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly CsrfConfig $csrfConfig = new CsrfConfig(),
        private readonly SanitizerConfig $sanitizerConfig = new SanitizerConfig(),
        private readonly RateLimitConfig $rateLimitConfig = new RateLimitConfig(),
        private readonly SigningConfig $signingConfig = new SigningConfig(secret: ''),
        private readonly AuditConfig $auditConfig = new AuditConfig(),
    ) {
    }

    public function csrf(): CsrfPortInterface
    {
        return new SessionCsrfService(
            $this->cache,
            new CsrfTokenGenerator(),
            $this->csrfConfig,
        );
    }

    public function sanitizer(): SanitizerPortInterface
    {
        return new HtmlSanitizer($this->sanitizerConfig);
    }

    public function rateLimiter(): RateLimiterPortInterface
    {
        return new CacheRateLimiter($this->cache, $this->rateLimitConfig);
    }

    public function requestSigner(): RequestSignerPortInterface
    {
        return new HmacRequestSigner($this->cache, $this->signingConfig);
    }

    public function auditLogger(): AuditLoggerPortInterface
    {
        return new PsrAuditLogger($this->logger, $this->auditConfig);
    }

    public function bearerAuth(): AuthPortInterface
    {
        return new BearerTokenAuthAdapter();
    }

    public function sessionAuth(): AuthPortInterface
    {
        return new SessionAuthAdapter();
    }

    public function jwtAuth(string $secret): AuthPortInterface
    {
        return new JwtAuthAdapter($secret);
    }

    public function policy(PolicyPortInterface ...$policies): PolicyPortInterface
    {
        return new Policy\PolicyChain(...$policies);
    }
}
