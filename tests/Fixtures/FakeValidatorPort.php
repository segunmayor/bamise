<?php

declare(strict_types=1);

namespace Bamise\Tests\Fixtures;

use Bamise\Contract\ValidatorPortInterface;
use Bamise\Contract\ValueObject\ValidationResult;

final class FakeValidatorPort implements ValidatorPortInterface
{
    public function __construct(
        private bool $valid = true,
        /** @var array<string, mixed> */
        private array $errors = [],
    ) {
    }

    public function validate(array $data, array $rules): ValidationResult
    {
        unset($rules);

        return new ValidationResult(
            valid: $this->valid,
            errors: $this->errors,
            sanitizedData: $data,
        );
    }
}
