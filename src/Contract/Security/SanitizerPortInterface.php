<?php

declare(strict_types=1);

namespace Bamise\Contract\Security;

interface SanitizerPortInterface
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sanitize(array $data): array;
}
