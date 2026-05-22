<?php

declare(strict_types=1);

namespace Bamise\Contract\Security;

use Bamise\Contract\Http\CrudRequestInterface;

interface RequestSignerPortInterface
{
    public function verify(CrudRequestInterface $request): bool;

    public function sign(array $payload): string;
}
