<?php

declare(strict_types=1);

namespace Bamise\Tests\Fixtures;

use Bamise\Contract\Security\SanitizerPortInterface;

final class FakeSanitizerPort implements SanitizerPortInterface
{
    public function sanitize(array $data): array
    {
        return $data;
    }
}
