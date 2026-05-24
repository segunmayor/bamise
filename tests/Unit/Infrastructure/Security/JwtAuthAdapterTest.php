<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Infrastructure\Security\Auth\JwtAuthAdapter;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for JwtAuthAdapterTest.
 *
 * firebase/php-jwt is listed as a suggested dependency, not required.
 * Tests are split into two groups:
 *   - Always-run: verify fail-closed behaviour when the library is absent.
 *   - Conditional: exercise the JWT decode path only when the library IS present.
 */
final class JwtAuthAdapterTest extends TestCase
{
    // ── subject() ────────────────────────────────────────────────────────────

    public function test_subject_always_returns_null(): void
    {
        self::assertNull((new JwtAuthAdapter('secret'))->subject());
    }

    public function test_subject_returns_null_regardless_of_secret(): void
    {
        self::assertNull((new JwtAuthAdapter(''))->subject());
        self::assertNull((new JwtAuthAdapter(str_repeat('k', 64)))->subject());
    }

    // ── Fail-closed when library is absent ───────────────────────────────────

    public function test_authenticate_returns_null_when_firebase_jwt_unavailable(): void
    {
        if (class_exists(\Firebase\JWT\JWT::class)) {
            self::markTestSkipped('firebase/php-jwt is installed; fail-closed path not exercised.');
        }

        $result = (new JwtAuthAdapter('my-secret'))->authenticate(
            new FakeCrudRequest('GET', '/users'),
        );

        self::assertNull($result, 'Must be fail-closed when firebase/php-jwt is absent.');
    }

    public function test_authenticate_returns_null_with_no_authorization_header_when_library_absent(): void
    {
        if (class_exists(\Firebase\JWT\JWT::class)) {
            self::markTestSkipped('firebase/php-jwt is installed.');
        }

        $result = (new JwtAuthAdapter('secret'))->authenticate(
            new FakeCrudRequest('GET', '/users', [], []),
        );

        self::assertNull($result);
    }

    public function test_authenticate_returns_null_with_basic_auth_header_when_library_absent(): void
    {
        if (class_exists(\Firebase\JWT\JWT::class)) {
            self::markTestSkipped('firebase/php-jwt is installed.');
        }

        $result = (new JwtAuthAdapter('secret'))->authenticate(
            new FakeCrudRequest('GET', '/users', [], ['Authorization' => 'Basic dXNlcjpwYXNz']),
        );

        self::assertNull($result);
    }

    public function test_authenticate_returns_null_with_malformed_bearer_when_library_absent(): void
    {
        if (class_exists(\Firebase\JWT\JWT::class)) {
            self::markTestSkipped('firebase/php-jwt is installed.');
        }

        $result = (new JwtAuthAdapter('secret'))->authenticate(
            new FakeCrudRequest('GET', '/users', [], ['Authorization' => 'Bearer not.valid.jwt']),
        );

        self::assertNull($result);
    }

    public function test_custom_subject_claim_can_be_set(): void
    {
        $adapter = new JwtAuthAdapter('secret', 'user_id');
        self::assertNull($adapter->subject());
    }

    // ── Conditional: library IS present ─────────────────────────────────────

    public function test_authenticate_with_valid_hs256_token_returns_subject(): void
    {
        if (! class_exists(\Firebase\JWT\JWT::class) || ! class_exists(\Firebase\JWT\Key::class)) {
            self::markTestSkipped('firebase/php-jwt not installed.');
        }

        $secret  = 'test-secret-key-at-least-32-chars!!';
        $payload = ['sub' => 42, 'iat' => time(), 'exp' => time() + 3600];
        $token   = \Firebase\JWT\JWT::encode($payload, $secret, 'HS256');

        $result = (new JwtAuthAdapter($secret))->authenticate(
            new FakeCrudRequest('GET', '/api', [], ['Authorization' => "Bearer $token"]),
        );

        self::assertNotNull($result);
    }

    public function test_authenticate_with_expired_token_returns_null(): void
    {
        if (! class_exists(\Firebase\JWT\JWT::class) || ! class_exists(\Firebase\JWT\Key::class)) {
            self::markTestSkipped('firebase/php-jwt not installed.');
        }

        $secret  = 'test-secret';
        $payload = ['sub' => 1, 'iat' => time() - 7200, 'exp' => time() - 3600];
        $token   = \Firebase\JWT\JWT::encode($payload, $secret, 'HS256');

        $result = (new JwtAuthAdapter($secret))->authenticate(
            new FakeCrudRequest('GET', '/api', [], ['Authorization' => "Bearer $token"]),
        );

        self::assertNull($result);
    }

    public function test_authenticate_with_wrong_secret_returns_null(): void
    {
        if (! class_exists(\Firebase\JWT\JWT::class) || ! class_exists(\Firebase\JWT\Key::class)) {
            self::markTestSkipped('firebase/php-jwt not installed.');
        }

        $token = \Firebase\JWT\JWT::encode(
            ['sub' => 1, 'exp' => time() + 3600],
            'correct-secret',
            'HS256',
        );

        $result = (new JwtAuthAdapter('wrong-secret'))->authenticate(
            new FakeCrudRequest('GET', '/api', [], ['Authorization' => "Bearer $token"]),
        );

        self::assertNull($result);
    }

    public function test_authenticate_with_missing_sub_claim_returns_null(): void
    {
        if (! class_exists(\Firebase\JWT\JWT::class) || ! class_exists(\Firebase\JWT\Key::class)) {
            self::markTestSkipped('firebase/php-jwt not installed.');
        }

        $secret = 'test-secret';
        $token  = \Firebase\JWT\JWT::encode(['exp' => time() + 3600], $secret, 'HS256');

        $result = (new JwtAuthAdapter($secret))->authenticate(
            new FakeCrudRequest('GET', '/api', [], ['Authorization' => "Bearer $token"]),
        );

        self::assertNull($result);
    }

    public function test_authenticate_with_custom_claim_name(): void
    {
        if (! class_exists(\Firebase\JWT\JWT::class) || ! class_exists(\Firebase\JWT\Key::class)) {
            self::markTestSkipped('firebase/php-jwt not installed.');
        }

        $secret  = 'test-secret';
        $token   = \Firebase\JWT\JWT::encode(
            ['user_id' => 99, 'exp' => time() + 3600],
            $secret,
            'HS256',
        );

        $result = (new JwtAuthAdapter($secret, 'user_id'))->authenticate(
            new FakeCrudRequest('GET', '/api', [], ['Authorization' => "Bearer $token"]),
        );

        self::assertNotNull($result);
    }
}
