<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Infrastructure\Security\Auth\BearerTokenAuthAdapter;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for BearerTokenAuthAdapter.
 *
 * Kills escaped mutants:
 * - Line 35: PregMatchRemoveCaret/Dollar (regex anchors)
 * - Line 39: UnwrapTrim (trim on payload)
 * - Lines 46-47: parts array access / empty-string check for roles and permissions
 * - Lines 63-64: strtolower / CastString in authorizationHeader
 * - Line 68: IncrementInteger on $value[0]
 */
final class BearerTokenMutationTest extends TestCase
{
    // ── Line 35: regex anchor mutations ───────────────────────────────────────

    public function test_non_bearer_prefix_returns_null(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $request = new FakeCrudRequest(headers: ['Authorization' => 'Token abc123']);

        self::assertNull($adapter->authenticate($request));
    }

    public function test_bearer_without_space_returns_null(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $request = new FakeCrudRequest(headers: ['Authorization' => 'Bearerabc123']);

        self::assertNull($adapter->authenticate($request));
    }

    public function test_bearer_with_token_after_newline_returns_null(): void
    {
        // Caret removal: without ^ the regex could match mid-string "Bearer xxx"
        $adapter = new BearerTokenAuthAdapter();
        $request = new FakeCrudRequest(headers: ['Authorization' => "invalid\nBearer abc123"]);

        self::assertNull($adapter->authenticate($request));
    }

    public function test_bearer_with_spaces_in_token_captures_full_payload(): void
    {
        // The regex uses (.+) which captures all chars including spaces
        // "Bearer id|role|perm" → captures "id|role|perm" as full payload
        $adapter = new BearerTokenAuthAdapter();
        $request = new FakeCrudRequest(headers: ['Authorization' => 'Bearer 42|admin|read']);

        $subject = $adapter->authenticate($request);

        self::assertNotNull($subject);
        self::assertSame(42, $subject->id); // @phpstan-ignore-line
    }

    public function test_valid_bearer_is_authenticated(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $request = new FakeCrudRequest(headers: ['Authorization' => 'Bearer user-42']);

        $subject = $adapter->authenticate($request);

        self::assertNotNull($subject);
    }

    // ── Line 39: UnwrapTrim ───────────────────────────────────────────────────

    public function test_token_with_leading_whitespace_after_bearer(): void
    {
        // Bearer followed by many spaces — trim on payload prevents empty-string bypass
        $adapter = new BearerTokenAuthAdapter();
        // The regex \s+ already consumes whitespace, so the captured group is trim-safe.
        // But a Bearer with just spaces (no token) should fail.
        $request = new FakeCrudRequest(headers: ['Authorization' => 'Bearer    ']);

        // The \S+ requires at least one non-space char, so 'Bearer    ' (spaces only) fails
        self::assertNull($adapter->authenticate($request));
    }

    // ── Lines 46-47: roles/permissions empty-string checks ───────────────────

    public function test_bearer_with_roles_but_empty_permissions(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        // Format: {id}|{roles}|{permissions}
        // Empty permissions segment → permissions = []
        $request = new FakeCrudRequest(headers: ['Authorization' => 'Bearer 123|admin,editor|']);

        $subject = $adapter->authenticate($request);

        self::assertNotNull($subject);
        self::assertSame('admin', $subject->roles[0]);  // @phpstan-ignore-line
        self::assertSame([], $subject->permissions);     // @phpstan-ignore-line
    }

    public function test_bearer_with_empty_roles_but_permissions(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        // Empty roles segment → roles = []
        $request = new FakeCrudRequest(headers: ['Authorization' => 'Bearer 123||read,write']);

        $subject = $adapter->authenticate($request);

        self::assertNotNull($subject);
        self::assertSame([], $subject->roles);              // @phpstan-ignore-line
        self::assertSame(['read', 'write'], $subject->permissions); // @phpstan-ignore-line
    }

    public function test_bearer_without_any_roles_or_permissions(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $request = new FakeCrudRequest(headers: ['Authorization' => 'Bearer 42']);

        $subject = $adapter->authenticate($request);

        self::assertNotNull($subject);
        self::assertSame([], $subject->roles);       // @phpstan-ignore-line
        self::assertSame([], $subject->permissions); // @phpstan-ignore-line
    }

    public function test_bearer_with_multiple_roles(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $request = new FakeCrudRequest(headers: ['Authorization' => 'Bearer alice|admin,mod|posts.create']);

        $subject = $adapter->authenticate($request);

        self::assertNotNull($subject);
        self::assertSame(['admin', 'mod'], $subject->roles);         // @phpstan-ignore-line
        self::assertSame(['posts.create'], $subject->permissions);   // @phpstan-ignore-line
    }

    // ── Lines 63-64: strtolower / CastString in authorizationHeader ──────────

    public function test_uppercase_authorization_header_is_recognized(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $request = new FakeCrudRequest(headers: ['AUTHORIZATION' => 'Bearer id-99']);

        self::assertNotNull($adapter->authenticate($request));
    }

    public function test_mixed_case_authorization_header_is_recognized(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $request = new FakeCrudRequest(headers: ['aUtHoRiZaTiOn' => 'Bearer id-77']);

        self::assertNotNull($adapter->authenticate($request));
    }

    public function test_unrelated_header_does_not_match(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $request = new FakeCrudRequest(headers: [
            'X-Custom' => 'Bearer fake',
            'Accept' => '*/*',
        ]);

        self::assertNull($adapter->authenticate($request));
    }

    // ── Line 68: IncrementInteger on $value[0] ────────────────────────────────

    public function test_authorization_header_as_array_uses_first_element(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $request = new FakeCrudRequest(headers: [
            'Authorization' => ['Bearer user-5', 'Bearer ignored'],
        ]);

        $subject = $adapter->authenticate($request);

        self::assertNotNull($subject);
        self::assertSame('user-5', (string) $subject->id); // @phpstan-ignore-line
    }

    // ── Numeric vs string id branching ───────────────────────────────────────

    public function test_numeric_id_becomes_int(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $request = new FakeCrudRequest(headers: ['Authorization' => 'Bearer 42']);

        $subject = $adapter->authenticate($request);

        self::assertNotNull($subject);
        self::assertSame(42, $subject->id); // @phpstan-ignore-line
    }

    public function test_float_id_string_stays_string(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $request = new FakeCrudRequest(headers: ['Authorization' => 'Bearer 3.14']);

        $subject = $adapter->authenticate($request);

        self::assertNotNull($subject);
        self::assertSame('3.14', $subject->id); // @phpstan-ignore-line
    }

    public function test_non_numeric_id_stays_string(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $request = new FakeCrudRequest(headers: ['Authorization' => 'Bearer user-alice']);

        $subject = $adapter->authenticate($request);

        self::assertNotNull($subject);
        self::assertSame('user-alice', $subject->id); // @phpstan-ignore-line
    }

    public function test_authenticate_resets_resolved_subject_on_second_call(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $req1 = new FakeCrudRequest(headers: ['Authorization' => 'Bearer 1']);
        $req2 = new FakeCrudRequest(headers: []);

        $adapter->authenticate($req1);
        $result = $adapter->authenticate($req2);

        self::assertNull($result);
        self::assertNull($adapter->subject());
    }
}
