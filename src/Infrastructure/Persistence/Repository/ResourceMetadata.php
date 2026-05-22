<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Persistence\Repository;

use Bamise\Contract\Crud\ResourceDefinitionInterface;

readonly class ResourceMetadata
{
    /**
     * @param list<string> $fillable
     */
    public function __construct(
        public string $table,
        public string $primaryKey,
        public array $fillable,
    ) {
    }

    public static function from(ResourceDefinitionInterface $definition): self
    {
        return new self(
            $definition->table(),
            $definition->primaryKey(),
            $definition->fillable(),
        );
    }
}
