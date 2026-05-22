<?php

declare(strict_types=1);

namespace Bamise\Tests\Fixtures;

use Bamise\Contract\Crud\ResourceDefinitionInterface;
use Bamise\Contract\Enum\OperationType;

final class FakeResourceDefinition implements ResourceDefinitionInterface
{
    /**
     * @param list<string>              $fillable
     * @param list<string>              $guarded
     * @param array<string, mixed>      $rules
     * @param list<class-string>        $policyClasses
     */
    public function __construct(
        private string $table = 'users',
        private string $primaryKey = 'id',
        private array $fillable = ['name', 'email'],
        private array $guarded = ['id'],
        private array $rules = ['name' => 'required'],
        private array $policyClasses = [],
    ) {
    }

    public function table(): string
    {
        return $this->table;
    }

    public function primaryKey(): string
    {
        return $this->primaryKey;
    }

    public function fillable(): array
    {
        return $this->fillable;
    }

    public function guarded(): array
    {
        return $this->guarded;
    }

    public function rules(OperationType $operation): array
    {
        unset($operation);

        return $this->rules;
    }

    public function policyClasses(): array
    {
        return $this->policyClasses;
    }
}
