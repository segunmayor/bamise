<?php

declare(strict_types=1);

namespace Bamise\Contract\Security;

use Bamise\Contract\Http\CrudRequestInterface;

interface CsrfPortInterface
{
    public function validate(CrudRequestInterface $request): bool;

    public function generateToken(): string;
}
