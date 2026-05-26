<?php

declare(strict_types=1);

namespace App\Resource;

use Bamise\Contract\Crud\ResourceDefinitionInterface;
use Bamise\Contract\Enum\OperationType;

final class UploadDefinition implements ResourceDefinitionInterface
{
    public function table(): string      { return 'uploads'; }
    public function primaryKey(): string { return 'id'; }

    public function fillable(): array
    {
        return ['original_name', 'stored_filename', 'size', 'mime_type', 'uploaded_at'];
    }

    public function guarded(): array
    {
        return ['id'];
    }

    public function rules(OperationType $operation): array
    {
        return [];
    }

    public function policyClasses(): array
    {
        return [];
    }
}
