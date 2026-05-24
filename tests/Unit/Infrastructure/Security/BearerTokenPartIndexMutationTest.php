<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Infrastructure\Security\Auth\BearerTokenAuthAdapter;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for BearerTokenAuthAdapter part indexing.
 *
 * Kills escaped mutants:
 * - Line 46: IncrementInteger — isset($parts[2]) instead of isset($parts[1]) for roles check
 *   (token "id|roles" with 2 parts must still detect the roles part)
 * - Line 47: DecrementInteger — isset($parts[1]) instead of isset($parts[2]) for perms check
 *   (token "id|roles|perms" — changing which isset triggers different behavior)
 * - Line 64: Continue_ — non-Authorization headers must be skipped (not break early)
 */
final class BearerTokenPartIndexMutationTest extends TestCase
{
    private function makeRequest(string $token, array $extraHeaders = []): FakeCrudRequest
    {
        return new FakeCrudRequest(
            headers: array_merge(['Authorization' => "Bearer $token"], $extraHeaders),
        );
    }

    // ── Line 46: IncrementInteger — 2-part token has roles ───────────────────

    public function test_two_part_token_has_roles(): void
    {
        // Token: "42|admin,editor" — parts[0]=42, parts[1]='admin,editor'
        // Original: isset($parts[1]) && $parts[1] !== '' → true → roles=['admin','editor']
        // Mutant: isset($parts[2]) → false (only 2 parts) → roles=[]
        $adapter = new BearerTokenAuthAdapter();
        $subject = $adapter->authenticate($this->makeRequest('42|admin,editor'));

        self::assertNotNull($subject);
        self::assertSame(['admin', 'editor'], $subject->roles);
    }

    public function test_single_part_token_has_no_roles(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $subject = $adapter->authenticate($this->makeRequest('42'));

        self::assertNotNull($subject);
        self::assertSame([], $subject->roles);
    }

    public function test_empty_roles_part_gives_empty_roles(): void
    {
        // Token: "42||read" — parts[1]='' → roles=[] even if isset(parts[1])
        $adapter = new BearerTokenAuthAdapter();
        $subject = $adapter->authenticate($this->makeRequest('42||read'));

        self::assertNotNull($subject);
        self::assertSame([], $subject->roles);
        self::assertSame(['read'], $subject->permissions);
    }

    // ── Line 47: DecrementInteger — 3-part token has permissions ─────────────

    public function test_three_part_token_has_permissions(): void
    {
        // Token: "42|admin|read,write" — parts[2]='read,write'
        // Original: isset($parts[2]) && $parts[2] !== '' → true → perms=['read','write']
        // Mutant: isset($parts[1]) → true (always for 3-part tokens), but $parts[2] !== '' also needed
        // Both might give same result — this verifies basic functionality
        $adapter = new BearerTokenAuthAdapter();
        $subject = $adapter->authenticate($this->makeRequest('42|admin|read,write'));

        self::assertNotNull($subject);
        self::assertSame(['admin'], $subject->roles);
        self::assertSame(['read', 'write'], $subject->permissions);
    }

    public function test_two_part_token_has_no_permissions(): void
    {
        // Token: "42|admin" — only 2 parts, no perms part
        // Original: isset($parts[2]) → false → perms=[]
        // Mutant (isset($parts[1])): true AND $parts[2] === undefined → PHP would return null → null !== '' → true → explode null...
        // But PHP 8+ raises warning for undefined index, not fatal, returns null.
        // explode(',', '') = [''] but if $parts[2] is undefined...
        $adapter = new BearerTokenAuthAdapter();
        $subject = $adapter->authenticate($this->makeRequest('42|admin'));

        self::assertNotNull($subject);
        self::assertSame([], $subject->permissions, 'Two-part token must have empty permissions');
    }

    // ── Line 64: Continue_ — non-Authorization headers are skipped ───────────

    public function test_authorization_header_found_after_other_headers(): void
    {
        // If Continue_ → break, non-Authorization header breaks loop and Authorization is never found
        $adapter = new BearerTokenAuthAdapter();
        $request = new FakeCrudRequest(
            headers: [
                'Content-Type' => 'application/json',  // unrelated header
                'X-Custom' => 'value',                  // unrelated header
                'Authorization' => 'Bearer user123',    // target header (after others)
            ],
        );

        $subject = $adapter->authenticate($request);

        self::assertNotNull($subject, 'Authorization header must be found even after other headers');
        self::assertSame('user123', $subject->id);
    }
}
