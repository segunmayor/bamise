<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit;

use Bamise\Application\Config\MiddlewareConfig;
use Bamise\Application\Middleware\AuditMiddleware;
use Bamise\Application\Middleware\AuthenticationMiddleware;
use Bamise\Application\Middleware\AuthorizeMiddleware;
use Bamise\Application\Middleware\CsrfMiddleware;
use Bamise\Application\Middleware\RateLimitMiddleware;
use Bamise\Application\Middleware\SanitizeMiddleware;
use Bamise\Application\Middleware\ValidateMiddleware;
use Bamise\Infrastructure\Security\Csrf\CsrfConfig;
use Bamise\Infrastructure\Security\Csrf\CsrfTokenGenerator;
use Bamise\Infrastructure\Security\RateLimit\RateLimitConfig;
use Bamise\Infrastructure\Security\Signing\SigningConfig;
use PHPUnit\Framework\TestCase;

/**
 * Targeted tests to kill integer/string mutation survivors.
 * Each test pins an exact value that Infection would increment/decrement.
 */
final class MutationKillerTest extends TestCase
{
    // ── CsrfConfig defaults ───────────────────────────────────────────────────

    public function test_csrf_config_default_field_name(): void
    {
        self::assertSame('_csrf', (new CsrfConfig())->fieldName);
    }

    public function test_csrf_config_default_token_length_is_32(): void
    {
        self::assertSame(32, (new CsrfConfig())->tokenLength);
    }

    public function test_csrf_config_default_ttl_is_3600(): void
    {
        self::assertSame(3600, (new CsrfConfig())->ttlSeconds);
    }

    public function test_csrf_config_default_session_field(): void
    {
        self::assertSame('_session_id', (new CsrfConfig())->sessionField);
    }

    public function test_csrf_config_default_session_id(): void
    {
        self::assertSame('default', (new CsrfConfig())->defaultSessionId);
    }

    // ── SigningConfig defaults ─────────────────────────────────────────────────

    public function test_signing_config_default_max_skew_is_300(): void
    {
        self::assertSame(300, (new SigningConfig(secret: 'x'))->maxSkewSeconds);
    }

    public function test_signing_config_default_nonce_ttl_is_600(): void
    {
        self::assertSame(600, (new SigningConfig(secret: 'x'))->nonceTtlSeconds);
    }

    public function test_signing_config_default_timestamp_header(): void
    {
        self::assertSame('X-Bamise-Timestamp', (new SigningConfig(secret: 'x'))->timestampHeader);
    }

    public function test_signing_config_default_nonce_header(): void
    {
        self::assertSame('X-Bamise-Nonce', (new SigningConfig(secret: 'x'))->nonceHeader);
    }

    public function test_signing_config_default_signature_header(): void
    {
        self::assertSame('X-Bamise-Signature', (new SigningConfig(secret: 'x'))->signatureHeader);
    }

    // ── RateLimitConfig defaults ──────────────────────────────────────────────

    public function test_rate_limit_config_default_max_attempts_is_60(): void
    {
        self::assertSame(60, (new RateLimitConfig())->maxAttempts);
    }

    public function test_rate_limit_config_default_window_is_60(): void
    {
        self::assertSame(60, (new RateLimitConfig())->windowSeconds);
    }

    // ── CsrfTokenGenerator: min() boundary ───────────────────────────────────

    public function test_csrf_token_generator_uses_at_least_1_byte(): void
    {
        $gen = new CsrfTokenGenerator();

        // byteLength=0 → max(1,0) = 1 → bin2hex(1 byte) = 2 hex chars
        $token = $gen->generate(0);

        self::assertGreaterThanOrEqual(2, strlen($token));
    }

    public function test_csrf_token_generator_negative_length_uses_minimum(): void
    {
        $gen = new CsrfTokenGenerator();

        $token = $gen->generate(-5);

        // Still at least 1 byte = 2 hex chars minimum
        self::assertGreaterThanOrEqual(2, strlen($token));
    }

    public function test_csrf_token_generator_length_32_produces_64_char_hex(): void
    {
        $gen = new CsrfTokenGenerator();

        $token = $gen->generate(32);

        self::assertSame(64, strlen($token));
    }

    public function test_csrf_token_generator_output_is_hex(): void
    {
        $gen = new CsrfTokenGenerator();

        self::assertMatchesRegularExpression('/^[0-9a-f]+$/', $gen->generate(16));
    }

    // ── MiddlewareConfig::defaults() exact priorities ─────────────────────────

    public function test_middleware_config_defaults_contains_all_seven_middleware(): void
    {
        $config = MiddlewareConfig::defaults();

        self::assertCount(7, $config->middleware);
    }

    public function test_middleware_config_rate_limit_priority_is_100(): void
    {
        $config = MiddlewareConfig::defaults();
        $entry = $this->findMiddlewareEntry($config, RateLimitMiddleware::class);

        self::assertSame(100, $entry['priority']);
    }

    public function test_middleware_config_authentication_priority_is_200(): void
    {
        $config = MiddlewareConfig::defaults();
        $entry = $this->findMiddlewareEntry($config, AuthenticationMiddleware::class);

        self::assertSame(200, $entry['priority']);
    }

    public function test_middleware_config_csrf_priority_is_300(): void
    {
        $config = MiddlewareConfig::defaults();
        $entry = $this->findMiddlewareEntry($config, CsrfMiddleware::class);

        self::assertSame(300, $entry['priority']);
    }

    public function test_middleware_config_sanitize_priority_is_400(): void
    {
        $config = MiddlewareConfig::defaults();
        $entry = $this->findMiddlewareEntry($config, SanitizeMiddleware::class);

        self::assertSame(400, $entry['priority']);
    }

    public function test_middleware_config_validate_priority_is_500(): void
    {
        $config = MiddlewareConfig::defaults();
        $entry = $this->findMiddlewareEntry($config, ValidateMiddleware::class);

        self::assertSame(500, $entry['priority']);
    }

    public function test_middleware_config_authorize_priority_is_600(): void
    {
        $config = MiddlewareConfig::defaults();
        $entry = $this->findMiddlewareEntry($config, AuthorizeMiddleware::class);

        self::assertSame(600, $entry['priority']);
    }

    public function test_middleware_config_audit_priority_is_900(): void
    {
        $config = MiddlewareConfig::defaults();
        $entry = $this->findMiddlewareEntry($config, AuditMiddleware::class);

        self::assertSame(900, $entry['priority']);
    }

    /**
     * @param array{middleware: list<array{class: class-string, priority: int}>} $config
     * @return array{class: class-string, priority: int}
     */
    private function findMiddlewareEntry(MiddlewareConfig $config, string $class): array
    {
        foreach ($config->middleware as $entry) {
            if ($entry['class'] === $class) {
                return $entry;
            }
        }

        throw new \RuntimeException("Middleware {$class} not found in defaults.");
    }
}
