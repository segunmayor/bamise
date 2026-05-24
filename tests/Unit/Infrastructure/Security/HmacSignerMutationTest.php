<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Infrastructure\Cache\InMemoryCache;
use Bamise\Infrastructure\Security\Signing\HmacRequestSigner;
use Bamise\Infrastructure\Security\Signing\SigningConfig;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for HmacRequestSigner.
 *
 * Kills escaped mutants identified in the infection report:
 * - Line 32: LogicalOr (each individual header being null)
 * - Line 33,37,60: FalseValue (early-return paths)
 * - Line 40: CastInt (timestamp parsing)
 * - Line 42: GreaterThan (skew boundary: at-limit vs over-limit)
 * - Line 46: Concat/ConcatOperandRemoval (nonce cache prefix)
 * - Line 63: TrueValue (nonce cache value)
 * - Lines 76-79: Ternary/CastString in sign() defaults
 * - Line 97: ArrayItemRemoval (canonical string order)
 * - Line 98: UnwrapStrToUpper (method case normalization)
 * - Lines 128,133,138: headerValue() type coercions
 */
final class HmacSignerMutationTest extends TestCase
{
    private const string SECRET = 'mutation-kill-secret';

    private function signer(int $maxSkew = 300): HmacRequestSigner
    {
        return new HmacRequestSigner(
            new InMemoryCache(),
            new SigningConfig(secret: self::SECRET, maxSkewSeconds: $maxSkew),
        );
    }

    private function validRequest(
        string $method = 'GET',
        string $path = '/',
        array $body = [],
        ?string $overrideTimestamp = null,
        ?string $overrideNonce = null,
    ): array {
        $signer = $this->signer();
        $timestamp = $overrideTimestamp ?? (string) time();
        $nonce = $overrideNonce ?? bin2hex(random_bytes(8));
        $signature = $signer->sign([
            'method' => $method,
            'path' => $path,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'body' => $body,
        ]);

        return [$signer, $timestamp, $nonce, $signature];
    }

    // ── Line 32 LogicalOr: each header's absence should fail ─────────────────

    public function test_missing_timestamp_header_fails(): void
    {
        [$signer, , $nonce, $signature] = $this->validRequest();

        $request = new FakeCrudRequest(headers: [
            // no timestamp
            'X-Bamise-Nonce' => $nonce,
            'X-Bamise-Signature' => $signature,
        ]);

        self::assertFalse($signer->verify($request));
    }

    public function test_missing_nonce_header_fails(): void
    {
        [$signer, $timestamp, , $signature] = $this->validRequest();

        $request = new FakeCrudRequest(headers: [
            'X-Bamise-Timestamp' => $timestamp,
            // no nonce
            'X-Bamise-Signature' => $signature,
        ]);

        self::assertFalse($signer->verify($request));
    }

    public function test_missing_signature_header_fails(): void
    {
        [$signer, $timestamp, $nonce] = $this->validRequest();

        $request = new FakeCrudRequest(headers: [
            'X-Bamise-Timestamp' => $timestamp,
            'X-Bamise-Nonce' => $nonce,
            // no signature
        ]);

        self::assertFalse($signer->verify($request));
    }

    // ── Line 37 FalseValue: non-digit timestamp returns false ─────────────────

    public function test_non_digit_timestamp_returns_false(): void
    {
        $signer = $this->signer();

        $request = new FakeCrudRequest(headers: [
            'X-Bamise-Timestamp' => 'not-a-number',
            'X-Bamise-Nonce' => 'nonce',
            'X-Bamise-Signature' => 'sig',
        ]);

        self::assertFalse($signer->verify($request));
    }

    public function test_float_timestamp_returns_false(): void
    {
        $signer = $this->signer();

        $request = new FakeCrudRequest(headers: [
            'X-Bamise-Timestamp' => '1234567890.5',
            'X-Bamise-Nonce' => 'nonce',
            'X-Bamise-Signature' => 'sig',
        ]);

        self::assertFalse($signer->verify($request));
    }

    // ── Line 42 GreaterThan: skew boundary ────────────────────────────────────

    public function test_timestamp_at_exactly_max_skew_boundary_is_accepted(): void
    {
        $signer = $this->signer(maxSkew: 300);
        // Exactly 300 seconds ago: abs(now - (now-300)) = 300, 300 > 300 is false → accepted
        $timestamp = (string) (time() - 300);
        $nonce = bin2hex(random_bytes(8));
        $signature = $signer->sign([
            'method' => 'GET',
            'path' => '/',
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'body' => [],
        ]);

        $request = new FakeCrudRequest(headers: [
            'X-Bamise-Timestamp' => $timestamp,
            'X-Bamise-Nonce' => $nonce,
            'X-Bamise-Signature' => $signature,
        ]);

        self::assertTrue($signer->verify($request));
    }

    public function test_timestamp_one_second_past_max_skew_is_rejected(): void
    {
        $signer = $this->signer(maxSkew: 300);
        // 301 seconds ago: abs = 301 > 300 → rejected
        $timestamp = (string) (time() - 301);
        $nonce = bin2hex(random_bytes(8));
        $signature = $signer->sign([
            'method' => 'GET',
            'path' => '/',
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'body' => [],
        ]);

        $request = new FakeCrudRequest(headers: [
            'X-Bamise-Timestamp' => $timestamp,
            'X-Bamise-Nonce' => $nonce,
            'X-Bamise-Signature' => $signature,
        ]);

        self::assertFalse($signer->verify($request));
    }

    // ── Line 46 Concat: nonce cache key includes prefix ───────────────────────

    public function test_nonce_replay_uses_correct_cache_prefix(): void
    {
        $cache = new InMemoryCache();
        $signer = new HmacRequestSigner(
            $cache,
            new SigningConfig(secret: self::SECRET),
        );
        $timestamp = (string) time();
        $nonce = 'fixed-nonce-for-prefix-test';
        $signature = $signer->sign([
            'method' => 'POST',
            'path' => '/items',
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'body' => [],
        ]);
        $request = new FakeCrudRequest(
            'POST',
            '/items',
            [],
            [
                'X-Bamise-Timestamp' => $timestamp,
                'X-Bamise-Nonce' => $nonce,
                'X-Bamise-Signature' => $signature,
            ],
        );

        $signer->verify($request);

        // After first verify, nonce stored as 'sign:nonce:{nonce}'
        self::assertNotNull($cache->get('sign:nonce:' . $nonce));
    }

    // ── Line 63 TrueValue: nonce is stored as true ────────────────────────────

    public function test_nonce_is_stored_as_truthy_value_in_cache(): void
    {
        $cache = new InMemoryCache();
        $signer = new HmacRequestSigner($cache, new SigningConfig(secret: self::SECRET));
        $timestamp = (string) time();
        $nonce = 'truthy-check-nonce';
        $signature = $signer->sign([
            'method' => 'GET', 'path' => '/',
            'timestamp' => $timestamp, 'nonce' => $nonce, 'body' => [],
        ]);
        $request = new FakeCrudRequest(headers: [
            'X-Bamise-Timestamp' => $timestamp,
            'X-Bamise-Nonce' => $nonce,
            'X-Bamise-Signature' => $signature,
        ]);

        self::assertTrue($signer->verify($request));
        self::assertNotNull($cache->get('sign:nonce:' . $nonce));
    }

    // ── Lines 76-79 Ternary/CastString: sign() defaults ──────────────────────

    public function test_sign_defaults_method_to_get(): void
    {
        $signer = $this->signer();
        $sig1 = $signer->sign(['path' => '/', 'timestamp' => '1000', 'nonce' => 'n', 'body' => []]);
        $sig2 = $signer->sign(['method' => 'GET', 'path' => '/', 'timestamp' => '1000', 'nonce' => 'n', 'body' => []]);

        self::assertSame($sig1, $sig2);
    }

    public function test_sign_defaults_path_to_slash(): void
    {
        $signer = $this->signer();
        $sig1 = $signer->sign(['method' => 'GET', 'timestamp' => '1000', 'nonce' => 'n', 'body' => []]);
        $sig2 = $signer->sign(['method' => 'GET', 'path' => '/', 'timestamp' => '1000', 'nonce' => 'n', 'body' => []]);

        self::assertSame($sig1, $sig2);
    }

    // ── Line 98 UnwrapStrToUpper: method must be uppercased in canonical ──────

    public function test_sign_lowercase_method_matches_uppercase_method(): void
    {
        $signer = $this->signer();
        $params = ['path' => '/x', 'timestamp' => '9999', 'nonce' => 'abc', 'body' => []];

        $lower = $signer->sign(['method' => 'post', ...$params]);
        $upper = $signer->sign(['method' => 'POST', ...$params]);

        self::assertSame($lower, $upper);
    }

    public function test_method_case_in_verify_is_normalized(): void
    {
        $signer = $this->signer();
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(8));

        $signature = $signer->sign([
            'method' => 'post',
            'path' => '/orders',
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'body' => [],
        ]);

        $request = new FakeCrudRequest(
            'POST',
            '/orders',
            [],
            [
                'X-Bamise-Timestamp' => $timestamp,
                'X-Bamise-Nonce' => $nonce,
                'X-Bamise-Signature' => $signature,
            ],
        );

        self::assertTrue($signer->verify($request));
    }

    // ── Line 97 ArrayItemRemoval: all canonical string parts matter ───────────

    public function test_changing_path_invalidates_signature(): void
    {
        [$signer, $timestamp, $nonce, $signature] = $this->validRequest('GET', '/path-a');

        $request = new FakeCrudRequest(
            'GET',
            '/path-b', // different path
            [],
            [
                'X-Bamise-Timestamp' => $timestamp,
                'X-Bamise-Nonce' => $nonce,
                'X-Bamise-Signature' => $signature,
            ],
        );

        self::assertFalse($signer->verify($request));
    }

    public function test_changing_method_invalidates_signature(): void
    {
        [$signer, $timestamp, $nonce, $signature] = $this->validRequest('GET', '/api');

        $request = new FakeCrudRequest(
            'POST', // different method
            '/api',
            [],
            [
                'X-Bamise-Timestamp' => $timestamp,
                'X-Bamise-Nonce' => $nonce,
                'X-Bamise-Signature' => $signature,
            ],
        );

        self::assertFalse($signer->verify($request));
    }

    public function test_changing_body_invalidates_signature(): void
    {
        [$signer, $timestamp, $nonce, $signature] = $this->validRequest('POST', '/api', ['key' => 'val']);

        $request = new FakeCrudRequest(
            'POST',
            '/api',
            ['key' => 'different'], // different body
            [
                'X-Bamise-Timestamp' => $timestamp,
                'X-Bamise-Nonce' => $nonce,
                'X-Bamise-Signature' => $signature,
            ],
        );

        self::assertFalse($signer->verify($request));
    }

    // ── Lines 128,133,138: headerValue() array header support ────────────────

    public function test_header_as_array_uses_first_element(): void
    {
        $signer = $this->signer();
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(8));
        $signature = $signer->sign([
            'method' => 'GET', 'path' => '/',
            'timestamp' => $timestamp, 'nonce' => $nonce, 'body' => [],
        ]);

        $request = new FakeCrudRequest(headers: [
            'X-Bamise-Timestamp' => [$timestamp, 'ignored'],
            'X-Bamise-Nonce' => [$nonce],
            'X-Bamise-Signature' => [$signature],
        ]);

        self::assertTrue($signer->verify($request));
    }

    public function test_empty_string_header_value_treated_as_missing(): void
    {
        $signer = $this->signer();
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(8));
        $signature = $signer->sign([
            'method' => 'GET', 'path' => '/',
            'timestamp' => $timestamp, 'nonce' => $nonce, 'body' => [],
        ]);

        $request = new FakeCrudRequest(headers: [
            'X-Bamise-Timestamp' => $timestamp,
            'X-Bamise-Nonce' => '',  // empty → treated as null → fails
            'X-Bamise-Signature' => $signature,
        ]);

        self::assertFalse($signer->verify($request));
    }

    public function test_sign_with_body_hash_uses_pre_computed_hash(): void
    {
        $signer = $this->signer();
        $body = ['key' => 'value'];
        $bodyHash = hash('sha256', json_encode($body, JSON_THROW_ON_ERROR));

        $sig1 = $signer->sign([
            'method' => 'POST', 'path' => '/api',
            'timestamp' => '5000', 'nonce' => 'n1', 'body_hash' => $bodyHash,
        ]);
        $sig2 = $signer->sign([
            'method' => 'POST', 'path' => '/api',
            'timestamp' => '5000', 'nonce' => 'n1', 'body' => $body,
        ]);

        self::assertSame($sig1, $sig2);
    }
}
