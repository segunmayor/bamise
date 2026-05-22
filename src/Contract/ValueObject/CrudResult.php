<?php

declare(strict_types=1);

namespace Bamise\Contract\ValueObject;

readonly class CrudResult
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $errors
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public bool $success,
        public array $data = [],
        public array $errors = [],
        public array $meta = [],
    ) {
    }
}
