<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\Csrf;

final class CsrfTokenGenerator
{
    public function generate(int $byteLength = 32): string
    {
        return bin2hex(random_bytes(max(1, $byteLength)));
    }
}
