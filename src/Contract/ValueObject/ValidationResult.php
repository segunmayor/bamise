<?php

declare(strict_types=1);

namespace Bamise\Contract\ValueObject;

readonly class ValidationResult
{
    /**
     * @param array<string, mixed> $errors
     * @param array<string, mixed> $sanitizedData
     */
    public function __construct(
        public bool $valid,
        public array $errors = [],
        public array $sanitizedData = [],
    ) {
    }
}
