<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Persistence\Repository;

use Bamise\Contract\Crud\ResourceDefinitionInterface;
use Bamise\Contract\Persistence\ConnectionInterface;
use Bamise\Contract\Persistence\RepositoryInterface;
use Bamise\Infrastructure\Persistence\Query\SqlCompiler;

final class PdoRepositoryFactory
{
    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {
    }

    public function for(ResourceDefinitionInterface $definition): RepositoryInterface
    {
        $metadata = ResourceMetadata::from($definition);
        $compiler = new SqlCompiler($this->connection->dialect());

        return new PdoRepository(
            $this->connection,
            $compiler,
            $metadata->table,
            $metadata->primaryKey,
            $metadata->fillable,
        );
    }

    public function forMetadata(ResourceMetadata $metadata): RepositoryInterface
    {
        $compiler = new SqlCompiler($this->connection->dialect());

        return new PdoRepository(
            $this->connection,
            $compiler,
            $metadata->table,
            $metadata->primaryKey,
            $metadata->fillable,
        );
    }
}
