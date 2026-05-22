<?php

declare(strict_types=1);

namespace Bamise\Domain\Model;

readonly class Resource
{
    public function __construct(
        public string $name,
        public string $table,
        public string $primaryKey,
    ) {
    }

    public static function fromDefinition(string $name, string $table, string $primaryKey): self
    {
        return new self($name, $table, $primaryKey);
    }
}
