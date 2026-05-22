<?php

declare(strict_types=1);

namespace Bamise\Tests\Fixtures;

use Bamise\Contract\AuditLoggerPortInterface;
use Bamise\Contract\ValueObject\AuditRecord;

final class FakeAuditLoggerPort implements AuditLoggerPortInterface
{
    /** @var list<AuditRecord> */
    public array $records = [];

    public function log(AuditRecord $record): void
    {
        $this->records[] = $record;
    }
}
