<?php

declare(strict_types=1);

namespace Bamise\Contract;

use Bamise\Contract\Http\CrudRequestInterface;

interface AuthPortInterface
{
    public function subject(): ?object;

    public function authenticate(CrudRequestInterface $request): ?object;
}
