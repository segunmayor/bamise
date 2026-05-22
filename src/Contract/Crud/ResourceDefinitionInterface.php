<?php

declare(strict_types=1);

namespace Bamise\Contract\Crud;

use Bamise\Contract\Enum\OperationType;

interface ResourceDefinitionInterface
{
    public function table(): string;

    public function primaryKey(): string;

    /**
     * @return list<string>
     */
    public function fillable(): array;

    /**
     * @return list<string>
     */
    public function guarded(): array;

    /**
     * @return array<string, mixed>
     */
    public function rules(OperationType $operation): array;

    /**
     * @return list<class-string>
     */
    public function policyClasses(): array;
}
