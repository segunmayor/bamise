<?php

declare(strict_types=1);

namespace Bamise\Contract;

use Bamise\Contract\ValueObject\AuditRecord;

interface AuditLoggerPortInterface
{
    public function log(AuditRecord $record): void;
}
