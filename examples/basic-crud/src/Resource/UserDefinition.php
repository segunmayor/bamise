<?php

declare(strict_types=1);

namespace App\Resource;

use Bamise\Contract\Crud\ResourceDefinitionInterface;
use Bamise\Contract\Enum\OperationType;

final class UserDefinition implements ResourceDefinitionInterface
{
    public function table(): string
    {
        return 'users';
    }

    public function primaryKey(): string
    {
        return 'id';
    }

    public function fillable(): array
    {
        return ['name', 'email'];
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
