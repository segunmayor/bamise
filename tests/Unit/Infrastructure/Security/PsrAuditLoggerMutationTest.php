<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Contract\ValueObject\AuditRecord;
use Bamise\Infrastructure\Security\Audit\AuditConfig;
use Bamise\Infrastructure\Security\Audit\PsrAuditLogger;
use Bamise\Tests\Fixtures\TestLogger;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for PsrAuditLogger.
 *
 * Kills escaped mutants:
 * - Line 20: UnwrapArrayMap on normalizedRedactFields construction
 * - Line 21: UnwrapStrToLower on field name normalization
 * - Lines 33-38: ArrayItem mutations on all payload array keys
 * - Line 57: CastString on key comparison in redact()
 * - Line 67: Continue_ in redact() loop
 */
final class PsrAuditLoggerMutationTest extends TestCase
{
    private TestLogger $psr;
    private PsrAuditLogger $logger;

    protected function setUp(): void
    {
        $this->psr = new TestLogger();
        $this->logger = new PsrAuditLogger($this->psr, new AuditConfig(redactFields: ['password', 'Token']));
    }

    private function record(array $overrides = []): AuditRecord
    {
        return new AuditRecord(
            actor: $overrides['actor'] ?? 'user-1',
            action: $overrides['action'] ?? 'create',
            resource: $overrides['resource'] ?? 'products',
            recordId: $overrides['recordId'] ?? 42,
            ip: $overrides['ip'] ?? '127.0.0.1',
            userAgent: $overrides['userAgent'] ?? 'TestAgent',
            before: $overrides['before'] ?? null,
            after: $overrides['after'] ?? ['name' => 'Widget'],
            correlationId: $overrides['correlationId'] ?? null,
        );
    }

    // ── Lines 33-38: ArrayItem mutations — all payload keys must be present ───

    public function test_log_emits_actor(): void
    {
        $this->logger->log($this->record(['actor' => 'user-7']));

        $payload = $this->psr->entries[0]['context']['audit'];
        self::assertSame('user-7', $payload['actor']);
    }

    public function test_log_emits_action(): void
    {
        $this->logger->log($this->record(['action' => 'delete']));

        $payload = $this->psr->entries[0]['context']['audit'];
        self::assertSame('delete', $payload['action']);
    }

    public function test_log_emits_resource(): void
    {
        $this->logger->log($this->record(['resource' => 'orders']));

        $payload = $this->psr->entries[0]['context']['audit'];
        self::assertSame('orders', $payload['resource']);
    }

    public function test_log_emits_record_id(): void
    {
        $this->logger->log($this->record(['recordId' => 99]));

        $payload = $this->psr->entries[0]['context']['audit'];
        self::assertSame(99, $payload['record_id']);
    }

    public function test_log_emits_ip(): void
    {
        $this->logger->log($this->record(['ip' => '10.0.0.1']));

        $payload = $this->psr->entries[0]['context']['audit'];
        self::assertSame('10.0.0.1', $payload['ip']);
    }

    public function test_log_emits_user_agent(): void
    {
        $this->logger->log($this->record(['userAgent' => 'Mozilla']));

        $payload = $this->psr->entries[0]['context']['audit'];
        self::assertSame('Mozilla', $payload['user_agent']);
    }

    public function test_log_emits_before_field(): void
    {
        $this->logger->log($this->record(['before' => ['name' => 'Old']]));

        $payload = $this->psr->entries[0]['context']['audit'];
        self::assertSame(['name' => 'Old'], $payload['before']);
    }

    public function test_log_emits_after_field(): void
    {
        $this->logger->log($this->record(['after' => ['name' => 'New', 'price' => 9.99]]));

        $payload = $this->psr->entries[0]['context']['audit'];
        self::assertSame(['name' => 'New', 'price' => 9.99], $payload['after']);
    }

    public function test_log_emits_correlation_id(): void
    {
        $this->logger->log($this->record(['correlationId' => 'corr-xyz']));

        $payload = $this->psr->entries[0]['context']['audit'];
        self::assertSame('corr-xyz', $payload['correlation_id']);
    }

    // ── Line 20,21: UnwrapArrayMap/UnwrapStrToLower — normalization of redact fields ─

    public function test_redaction_works_for_lowercase_configured_field(): void
    {
        $this->logger->log($this->record(['after' => ['password' => 'secret123', 'name' => 'Widget']]));

        $payload = $this->psr->entries[0]['context']['audit'];
        self::assertSame('[REDACTED]', $payload['after']['password']);
        self::assertSame('Widget', $payload['after']['name']);
    }

    public function test_redaction_works_for_uppercase_configured_field(): void
    {
        // 'Token' is in redactFields with initial uppercase; strtolower normalizes it
        $this->logger->log($this->record(['after' => ['Token' => 'bearer-tok', 'name' => 'OK']]));

        $payload = $this->psr->entries[0]['context']['audit'];
        self::assertSame('[REDACTED]', $payload['after']['Token']);
    }

    // ── Line 57: CastString — key normalized to lowercase for comparison ──────

    public function test_redaction_matches_field_case_insensitively(): void
    {
        // 'PASSWORD' should match normalized redact field 'password'
        $this->logger->log($this->record(['after' => ['PASSWORD' => 'p4ss!']]));

        $payload = $this->psr->entries[0]['context']['audit'];
        self::assertSame('[REDACTED]', $payload['after']['PASSWORD']);
    }

    public function test_redaction_with_mixed_case_key(): void
    {
        $this->logger->log($this->record(['after' => ['Password' => 'mypass']]));

        $payload = $this->psr->entries[0]['context']['audit'];
        self::assertSame('[REDACTED]', $payload['after']['Password']);
    }

    // ── Line 67: Continue_ in redact() loop ─────────────────────────────────

    public function test_after_redacted_field_non_array_fields_still_pass_through(): void
    {
        // Tests that 'continue' in the redacted branch skips the is_array check
        $this->logger->log($this->record(['after' => [
            'password' => 'secret',
            'name' => 'Widget',
            'price' => 9.99,
        ]]));

        $payload = $this->psr->entries[0]['context']['audit'];
        self::assertSame('[REDACTED]', $payload['after']['password']);
        self::assertSame('Widget', $payload['after']['name']);
        self::assertSame(9.99, $payload['after']['price']);
    }

    public function test_nested_array_values_are_recursively_redacted(): void
    {
        $this->logger->log($this->record(['after' => [
            'user' => ['password' => 'nested-secret', 'email' => 'a@b.com'],
        ]]));

        $payload = $this->psr->entries[0]['context']['audit'];
        self::assertSame('[REDACTED]', $payload['after']['user']['password']);
        self::assertSame('a@b.com', $payload['after']['user']['email']);
    }

    public function test_null_before_field_remains_null(): void
    {
        $this->logger->log($this->record(['before' => null]));

        $payload = $this->psr->entries[0]['context']['audit'];
        self::assertNull($payload['before']);
    }

    // ── AuditConfig ArrayItemRemoval ─────────────────────────────────────────

    public function test_default_config_redacts_authorization_field(): void
    {
        $logger = new PsrAuditLogger($this->psr, new AuditConfig());
        $record = $this->record(['after' => ['authorization' => 'Bearer xyz', 'name' => 'OK']]);

        $logger->log($record);

        $payload = $this->psr->entries[0]['context']['audit'];
        self::assertSame('[REDACTED]', $payload['after']['authorization']);
    }
}
