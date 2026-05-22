<?php

declare(strict_types=1);

namespace Bamise\Contract\Enum;

enum OperationType: string
{
    case Create = 'create';
    case Read = 'read';
    case Update = 'update';
    case Delete = 'delete';
    case BulkDelete = 'bulk_delete';
    case BulkUpdate = 'bulk_update';
}
