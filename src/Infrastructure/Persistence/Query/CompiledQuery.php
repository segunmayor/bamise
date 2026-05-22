<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Persistence\Query;

readonly class CompiledQuery
{
    /**
     * @param array<string, mixed> $bindings
     */
    public function __construct(
        public string $sql,
        public array $bindings,
    ) {
    }
}
