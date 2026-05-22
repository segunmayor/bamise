<?php

declare(strict_types=1);

namespace Bamise\Tests\Fixtures;

use Bamise\Contract\Crud\ResourceDefinitionInterface;
use Bamise\Contract\Enum\OperationType;

final class TestUserResourceDefinition implements ResourceDefinitionInterface
{
    public function table(): string
    {
        return 'users';
    }

    public function primaryKey(): string
    {
        return 'id';
    }

    /**
     * @return list<string>
     */
    public function fillable(): array
    {
        return ['name', 'email'];
    }

    /**
     * @return list<string>
     */
    public function guarded(): array
    {
        return ['id'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(OperationType $operation): array
    {
        unset($operation);

        return [];
    }

    /**
     * @return list<class-string>
     */
    public function policyClasses(): array
    {
        return [];
    }
}
