<?php

declare(strict_types=1);

namespace App\Resource;

use App\Policy\ArticlePolicy;
use Bamise\Contract\Crud\ResourceDefinitionInterface;
use Bamise\Contract\Enum\OperationType;

final class ArticleDefinition implements ResourceDefinitionInterface
{
    public function table(): string      { return 'articles'; }
    public function primaryKey(): string { return 'id'; }

    public function fillable(): array
    {
        return ['author_id', 'title', 'body', 'published'];
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
        return [ArticlePolicy::class];
    }
}
