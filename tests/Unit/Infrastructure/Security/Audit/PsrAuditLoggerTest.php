<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security\Audit;

use Bamise\Contract\ValueObject\AuditRecord;
use Bamise\Infrastructure\Security\Audit\AuditConfig;
use Bamise\Infrastructure\Security\Audit\PsrAuditLogger;
use Bamise\Tests\Fixtures\TestLogger;
use PHPUnit\Framework\TestCase;

final class PsrAuditLoggerTest extends TestCase
{
    public function test_logs_audit_record_at_info_level(): void
    {
        $logger = new TestLogger();
        $audit = new PsrAuditLogger($logger, new AuditConfig());

        $audit->log($this->record());

        self::assertCount(1, $logger->entries);
        self::assertSame('info', $logger->entries[0]['level']);
        self::assertSame('audit', $logger->entries[0]['message']);
    }

    public function test_record_fields_are_present_in_context(): void
    {
        $logger = new TestLogger();
        $audit = new PsrAuditLogger($logger, new AuditConfig());

        $audit->log($this->record(actor: 'user-1', action: 'create', resource: 'orders'));

        $payload = $logger->entries[0]['context']['audit'];
        self::assertSame('user-1', $payload['actor']);
        self::assertSame('create', $payload['action']);
        self::assertSame('orders', $payload['resource']);
    }

    public function test_redacts_configured_fields_in_after_payload(): void
    {
        $logger = new TestLogger();
        $config = new AuditConfig(redactFields: ['password', 'token']);
        $audit = new PsrAuditLogger($logger, $config);

        $record = new AuditRecord(
            actor: null,
            action: 'create',
            resource: 'users',
            recordId: null,
            ip: null,
            userAgent: null,
            before: null,
            after: ['name' => 'Ada', 'password' => 'secret123', 'token' => 'abc'],
        );

        $audit->log($record);

        $after = $logger->entries[0]['context']['audit']['after'];
        self::assertSame('[REDACTED]', $after['password']);
        self::assertSame('[REDACTED]', $after['token']);
        self::assertSame('Ada', $after['name']);
    }

    public function test_redaction_is_case_insensitive(): void
    {
        $logger = new TestLogger();
        $config = new AuditConfig(redactFields: ['password']);
        $audit = new PsrAuditLogger($logger, $config);

        $record = new AuditRecord(
            actor: null,
            action: 'update',
            resource: 'users',
            recordId: null,
            ip: null,
            userAgent: null,
            after: ['Password' => 'secret'],
        );

        $audit->log($record);

        self::assertSame('[REDACTED]', $logger->entries[0]['context']['audit']['after']['Password']);
    }

    public function test_null_after_payload_remains_null(): void
    {
        $logger = new TestLogger();
        $audit = new PsrAuditLogger($logger, new AuditConfig());

        $audit->log($this->record());

        self::assertNull($logger->entries[0]['context']['audit']['after']);
    }

    private function record(
        string $actor = 'actor-1',
        string $action = 'create',
        string $resource = 'users',
    ): AuditRecord {
        return new AuditRecord(
            actor: $actor,
            action: $action,
            resource: $resource,
            recordId: null,
            ip: '127.0.0.1',
            userAgent: null,
        );
    }
}
