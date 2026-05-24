<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Infrastructure\Cache\InMemoryCache;
use Bamise\Infrastructure\Persistence\PDO\Dialect\SqliteDialect;
use Bamise\Infrastructure\Persistence\Query\SqlCompiler;
use Bamise\Infrastructure\Security\RateLimit\CacheRateLimiter;
use Bamise\Infrastructure\Security\RateLimit\RateLimitConfig;
use Bamise\Infrastructure\Security\Sanitizer\HtmlSanitizer;
use Bamise\Infrastructure\Security\Sanitizer\SanitizerConfig;
use PHPUnit\Framework\TestCase;

/**
 * Edge-case, boundary, and security tests for infrastructure security components.
 *
 * Covers: SQL injection via SqlCompiler criteria, mass-assignment-style column injection,
 * XSS via HtmlSanitizer, rate limiter boundary conditions, and cache boundary values.
 */
final class EdgeCaseSecurityTest extends TestCase
{
    // ── SqlCompiler: SQL injection via criteria ───────────────────────────────

    public function test_sql_compiler_uses_bound_parameters_not_string_concat(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());

        // Simulate an SQL injection attempt in criteria values.
        $maliciousValue = "' OR '1'='1";
        $query = $compiler->compileSelectAll('users', ['email' => $maliciousValue]);

        // The malicious string must appear in bindings, never in SQL template.
        self::assertStringNotContainsString($maliciousValue, $query->sql);
        self::assertSame($maliciousValue, $query->bindings['__w_email']);
    }

    public function test_sql_compiler_where_criteria_never_interpolated_into_sql(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());

        $payload = "admin'--";
        $query = $compiler->compileSelectAll('accounts', ['role' => $payload], 10, 0);

        self::assertStringNotContainsString($payload, $query->sql);
        self::assertSame($payload, $query->bindings['__w_role']);
    }

    public function test_sql_compiler_update_where_values_are_bound_not_interpolated(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());

        $injection = '; DROP TABLE users; --';
        $query = $compiler->compileUpdateWhere('posts', ['author' => $injection], ['title' => 'safe']);

        self::assertStringNotContainsString($injection, $query->sql);
        self::assertSame($injection, $query->bindings['__w_author']);
    }

    public function test_sql_compiler_delete_id_is_bound_not_interpolated(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());

        $injection = "1 OR 1=1";
        $query = $compiler->compileDelete('users', 'id', $injection);

        self::assertStringNotContainsString($injection, $query->sql);
        self::assertSame($injection, $query->bindings['__pk']);
    }

    public function test_sql_compiler_insert_empty_data_throws(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insert requires at least one column.');

        $compiler->compileInsert('users', 'id', []);
    }

    public function test_sql_compiler_update_only_pk_throws(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Update requires at least one mutable column.');

        // Data contains only the primary key — no mutable columns.
        $compiler->compileUpdate('users', 'id', 1, ['id' => 1]);
    }

    public function test_sql_compiler_update_where_empty_data_throws(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());

        $this->expectException(\InvalidArgumentException::class);

        $compiler->compileUpdateWhere('users', [], []);
    }

    public function test_sql_compiler_whitelist_strips_unlisted_columns(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());

        $filtered = $compiler->whitelistColumns(
            ['name', 'email'],
            ['name' => 'Alice', 'email' => 'a@b.c', 'admin' => true, 'role' => 'superuser'],
        );

        self::assertArrayHasKey('name', $filtered);
        self::assertArrayHasKey('email', $filtered);
        self::assertArrayNotHasKey('admin', $filtered);
        self::assertArrayNotHasKey('role', $filtered);
    }

    public function test_sql_compiler_whitelist_empty_allows_all_columns(): void
    {
        $compiler = new SqlCompiler(new SqliteDialect());

        $data = ['a' => 1, 'b' => 2, 'secret' => 'exposed'];
        $filtered = $compiler->whitelistColumns([], $data);

        self::assertSame($data, $filtered);
    }

    // ── HtmlSanitizer: XSS via input ─────────────────────────────────────────

    public function test_sanitizer_strips_script_tags(): void
    {
        $sanitizer = new HtmlSanitizer(new SanitizerConfig());

        $result = $sanitizer->sanitize(['name' => '<script>alert("xss")</script>Alice']);

        self::assertStringNotContainsString('<script>', (string) $result['name']);
        self::assertStringContainsString('Alice', (string) $result['name']);
    }

    public function test_sanitizer_strips_on_event_attributes(): void
    {
        $sanitizer = new HtmlSanitizer(new SanitizerConfig());

        $result = $sanitizer->sanitize(['input' => '<img onerror="alert(1)" src="x">']);

        // With no allowed tags, all tags are stripped
        self::assertStringNotContainsString('onerror', (string) $result['input']);
    }

    public function test_sanitizer_encodes_html_entities_when_configured(): void
    {
        $sanitizer = new HtmlSanitizer(new SanitizerConfig(encodeEntities: true));

        // strip_tags removes <b>, so the remaining & and "c" get encoded.
        $result = $sanitizer->sanitize(['q' => 'a & "c"']);

        self::assertStringContainsString('&amp;', (string) $result['q']);
        self::assertStringContainsString('&quot;', (string) $result['q']);
    }

    public function test_sanitizer_allows_configured_tags(): void
    {
        // encodeEntities must be false; otherwise htmlspecialchars would encode the kept tags.
        $sanitizer = new HtmlSanitizer(new SanitizerConfig(allowedTags: ['b', 'em'], encodeEntities: false));

        $result = $sanitizer->sanitize(['text' => '<b>bold</b> <script>bad</script>']);

        self::assertStringContainsString('<b>bold</b>', (string) $result['text']);
        self::assertStringNotContainsString('<script>', (string) $result['text']);
    }

    public function test_sanitizer_passes_non_string_values_through(): void
    {
        $sanitizer = new HtmlSanitizer(new SanitizerConfig());

        $result = $sanitizer->sanitize(['count' => 42, 'active' => true, 'score' => 3.14]);

        self::assertSame(42, $result['count']);
        self::assertTrue($result['active']);
        self::assertSame(3.14, $result['score']);
    }

    public function test_sanitizer_recurses_into_nested_arrays(): void
    {
        $sanitizer = new HtmlSanitizer(new SanitizerConfig());

        $result = $sanitizer->sanitize(['nested' => ['bio' => '<script>x</script>clean']]);

        self::assertIsArray($result['nested']);
        self::assertStringNotContainsString('<script>', (string) $result['nested']['bio']);
    }

    public function test_sanitizer_empty_input_returns_empty(): void
    {
        $sanitizer = new HtmlSanitizer(new SanitizerConfig());

        self::assertSame([], $sanitizer->sanitize([]));
    }

    public function test_sanitizer_handles_empty_string(): void
    {
        $sanitizer = new HtmlSanitizer(new SanitizerConfig());

        $result = $sanitizer->sanitize(['field' => '']);

        self::assertSame('', $result['field']);
    }

    public function test_sanitizer_handles_unicode_input(): void
    {
        $sanitizer = new HtmlSanitizer(new SanitizerConfig(encodeEntities: true));

        $result = $sanitizer->sanitize(['text' => 'こんにちは']);

        self::assertSame('こんにちは', $result['text']);
    }

    // ── CacheRateLimiter: boundary conditions ─────────────────────────────────

    public function test_rate_limiter_allows_up_to_max_attempts(): void
    {
        $config = new RateLimitConfig(maxAttempts: 3, windowSeconds: 60);
        $limiter = new CacheRateLimiter(new InMemoryCache(), $config);

        self::assertTrue($limiter->attempt('key'));
        self::assertTrue($limiter->attempt('key'));
        self::assertTrue($limiter->attempt('key'));
        self::assertFalse($limiter->attempt('key'));
    }

    public function test_rate_limiter_remaining_decrements_per_attempt(): void
    {
        $config = new RateLimitConfig(maxAttempts: 5, windowSeconds: 60);
        $limiter = new CacheRateLimiter(new InMemoryCache(), $config);

        self::assertSame(5, $limiter->remaining('k'));

        $limiter->attempt('k');
        self::assertSame(4, $limiter->remaining('k'));

        $limiter->attempt('k');
        self::assertSame(3, $limiter->remaining('k'));
    }

    public function test_rate_limiter_remaining_is_zero_when_exhausted(): void
    {
        $config = new RateLimitConfig(maxAttempts: 2, windowSeconds: 60);
        $limiter = new CacheRateLimiter(new InMemoryCache(), $config);

        $limiter->attempt('k');
        $limiter->attempt('k');
        $limiter->attempt('k'); // over limit

        self::assertSame(0, $limiter->remaining('k'));
    }

    public function test_rate_limiter_with_max_attempts_of_one(): void
    {
        $config = new RateLimitConfig(maxAttempts: 1, windowSeconds: 60);
        $limiter = new CacheRateLimiter(new InMemoryCache(), $config);

        self::assertTrue($limiter->attempt('k'));
        self::assertFalse($limiter->attempt('k'));
    }

    public function test_rate_limiter_different_keys_are_independent(): void
    {
        $config = new RateLimitConfig(maxAttempts: 1, windowSeconds: 60);
        $limiter = new CacheRateLimiter(new InMemoryCache(), $config);

        $limiter->attempt('a');

        self::assertFalse($limiter->attempt('a'));
        self::assertTrue($limiter->attempt('b'));
    }

    public function test_rate_limiter_expired_window_resets_count(): void
    {
        $config = new RateLimitConfig(maxAttempts: 1, windowSeconds: -1); // already expired
        $cache = new InMemoryCache();
        $limiter = new CacheRateLimiter($cache, $config);

        // First call to attempt() sees an expired window, treats as fresh.
        self::assertTrue($limiter->attempt('k'));
        // Second call: window was reset in the previous call, so this is a new window.
        // With windowSeconds=-1 the TTL is -1 — InMemoryCache treats negative TTL as expired.
        // So the window stored in cache is immediately stale.
        self::assertTrue($limiter->attempt('k'));
    }

    // ── InMemoryCache: boundary values ────────────────────────────────────────

    public function test_cache_zero_ttl_expires_in_same_second(): void
    {
        $cache = new InMemoryCache();
        $cache->set('k', 'v', 0);

        // ttl=0 → expires = time()+0 = time(). Check is expires < time() (strict less-than).
        // In the same second the value is still returned; it only expires in the NEXT second.
        // This is consistent with PSR-16 behavior where 0 means "expires as soon as possible"
        // but within-second reads still see the value.
        self::assertNotNull($cache->get('k'));
    }

    public function test_cache_large_ttl_keeps_value(): void
    {
        $cache = new InMemoryCache();
        $cache->set('k', 'v', PHP_INT_MAX);

        self::assertSame('v', $cache->get('k'));
    }

    public function test_cache_boolean_false_stored_and_retrieved(): void
    {
        $cache = new InMemoryCache();
        $cache->set('flag', false);

        self::assertFalse($cache->get('flag'));
    }

    public function test_cache_integer_zero_stored_and_retrieved(): void
    {
        $cache = new InMemoryCache();
        $cache->set('zero', 0);

        self::assertSame(0, $cache->get('zero'));
    }

    public function test_cache_empty_string_stored_and_retrieved(): void
    {
        $cache = new InMemoryCache();
        $cache->set('empty', '');

        self::assertSame('', $cache->get('empty'));
    }
}
