<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Infrastructure\Cache\InMemoryCache;
use Bamise\Infrastructure\Security\Csrf\CsrfConfig;
use Bamise\Infrastructure\Security\Csrf\CsrfTokenGenerator;
use Bamise\Infrastructure\Security\Csrf\SessionCsrfService;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for SessionCsrfService.
 *
 * Each test kills a specific mutant type identified in the infection report:
 * - Line 28: LogicalOr (sessionId null vs token null are both needed)
 * - Line 53: PublicVisibility (verify() must be callable externally)
 * - Line 81: LogicalAnd (non-string + empty-string session id in body)
 * - Line 85-96: Foreach / header resolution logic
 * - Line 96: LogicalAnd (candidate is_string && not empty)
 * - Line 106: LogicalAnd (token is_string && not empty)
 */
final class CsrfServiceMutationTest extends TestCase
{
    private SessionCsrfService $service;
    private InMemoryCache $cache;

    protected function setUp(): void
    {
        $this->cache = new InMemoryCache();
        $this->service = new SessionCsrfService(
            $this->cache,
            new CsrfTokenGenerator(),
            new CsrfConfig(),
        );
    }

    // ── Line 28 LogicalOr: both arms of || must matter ───────────────────────

    public function test_validate_returns_false_when_session_is_missing_but_token_present(): void
    {
        // No _session_id, so sessionId is null → should return false (first arm of OR)
        $request = new FakeCrudRequest(input: ['_csrf' => 'some-token']);

        self::assertFalse($this->service->validate($request));
    }

    public function test_validate_returns_false_when_token_is_missing_but_session_present(): void
    {
        // No _csrf field, so token is null → should return false (second arm of OR)
        $request = new FakeCrudRequest(input: ['_session_id' => 'sess-1']);

        self::assertFalse($this->service->validate($request));
    }

    // ── Line 53 PublicVisibility: verify() must remain public ────────────────

    public function test_verify_is_callable_directly(): void
    {
        $token = $this->service->generateForSession('direct-sess');

        self::assertTrue($this->service->verify($token, 'direct-sess'));
    }

    public function test_verify_returns_false_for_empty_stored_token(): void
    {
        // Store an empty string directly (bypasses generator)
        $this->cache->set('csrf:empty-sess', '');

        self::assertFalse($this->service->verify('any-token', 'empty-sess'));
    }

    public function test_verify_returns_false_when_no_token_in_cache(): void
    {
        self::assertFalse($this->service->verify('any', 'nonexistent-session'));
    }

    // ── Line 81 LogicalAnd: both is_string() and !== '' matter ───────────────

    public function test_session_id_from_input_as_empty_string_falls_through_to_header(): void
    {
        // Empty string in body → falls through to header lookup
        $token = $this->service->generateForSession('header-sess');
        $request = new FakeCrudRequest(
            input: ['_session_id' => '', '_csrf' => $token],
            headers: ['X-Session-Id' => 'header-sess'],
        );

        self::assertTrue($this->service->validate($request));
    }

    public function test_session_id_from_input_as_null_falls_through_to_header(): void
    {
        $token = $this->service->generateForSession('hdr-sess');
        $request = new FakeCrudRequest(
            input: ['_csrf' => $token],
            headers: ['X-Session-Id' => 'hdr-sess'],
        );

        self::assertTrue($this->service->validate($request));
    }

    // ── Lines 85-96: Header resolution (Foreach, Continue, case normalization) ─

    public function test_session_id_resolved_from_lowercase_header(): void
    {
        $token = $this->service->generateForSession('from-header');
        $request = new FakeCrudRequest(
            input: ['_csrf' => $token],
            headers: ['x-session-id' => 'from-header'],
        );

        self::assertTrue($this->service->validate($request));
    }

    public function test_session_id_resolved_from_uppercase_header(): void
    {
        $token = $this->service->generateForSession('upper-sess');
        $request = new FakeCrudRequest(
            input: ['_csrf' => $token],
            headers: ['X-SESSION-ID' => 'upper-sess'],
        );

        self::assertTrue($this->service->validate($request));
    }

    public function test_unrelated_headers_are_skipped_and_session_id_header_is_found(): void
    {
        $token = $this->service->generateForSession('amid-noise');
        $request = new FakeCrudRequest(
            input: ['_csrf' => $token],
            headers: [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer xyz',
                'X-Session-Id' => 'amid-noise',
                'Accept' => '*/*',
            ],
        );

        self::assertTrue($this->service->validate($request));
    }

    public function test_session_id_from_header_as_array_uses_first_element(): void
    {
        $token = $this->service->generateForSession('arr-sess');
        $request = new FakeCrudRequest(
            input: ['_csrf' => $token],
            headers: ['x-session-id' => ['arr-sess', 'ignored']],
        );

        self::assertTrue($this->service->validate($request));
    }

    // ── Line 96 LogicalAnd: candidate is_string && candidate !== '' ──────────

    public function test_empty_string_session_id_in_header_falls_back_to_null(): void
    {
        $request = new FakeCrudRequest(
            input: ['_csrf' => 'tok'],
            headers: ['x-session-id' => ''],
        );

        // Empty header → resolveSessionId returns null → validate returns false
        self::assertFalse($this->service->validate($request));
    }

    public function test_no_session_id_in_header_or_body_returns_false(): void
    {
        $request = new FakeCrudRequest(input: ['_csrf' => 'tok']);

        self::assertFalse($this->service->validate($request));
    }

    // ── Line 106 LogicalAnd: token is_string && token !== '' ─────────────────

    public function test_empty_string_token_in_input_is_treated_as_missing(): void
    {
        $request = new FakeCrudRequest(
            input: ['_session_id' => 'sess', '_csrf' => ''],
        );

        self::assertFalse($this->service->validate($request));
    }

    public function test_null_token_field_is_treated_as_missing(): void
    {
        $request = new FakeCrudRequest(input: ['_session_id' => 'sess']);

        self::assertFalse($this->service->validate($request));
    }

    // ── Cache prefix correctness ───────────────────────────────────────────────

    public function test_token_is_stored_with_csrf_prefix_plus_session_id(): void
    {
        $token = $this->service->generateForSession('prefix-check');

        // The cache key must be 'csrf:prefix-check'
        self::assertSame($token, $this->cache->get('csrf:prefix-check'));
    }

    public function test_different_sessions_use_different_cache_keys(): void
    {
        $tokenA = $this->service->generateForSession('sessA');
        $tokenB = $this->service->generateForSession('sessB');

        self::assertNotSame($tokenA, $tokenB);
        self::assertSame($tokenA, $this->cache->get('csrf:sessA'));
        self::assertSame($tokenB, $this->cache->get('csrf:sessB'));
    }

    // ── generateToken() uses defaultSessionId ─────────────────────────────────

    public function test_generate_token_uses_default_session_id(): void
    {
        $config = new CsrfConfig(defaultSessionId: 'my-default');
        $service = new SessionCsrfService($this->cache, new CsrfTokenGenerator(), $config);

        $token = $service->generateToken();

        // Must be stored under 'csrf:my-default'
        self::assertSame($token, $this->cache->get('csrf:my-default'));
    }
}
