<?php

declare(strict_types=1);

namespace Bamise\Contract;

use Bamise\Contract\ValueObject\ValidationResult;

interface ValidatorPortInterface
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     */
    public function validate(array $data, array $rules): ValidationResult;
}
