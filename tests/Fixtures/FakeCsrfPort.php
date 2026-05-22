<?php

declare(strict_types=1);

namespace Bamise\Tests\Fixtures;

use Bamise\Contract\Http\CrudRequestInterface;
use Bamise\Contract\Security\CsrfPortInterface;

final class FakeCsrfPort implements CsrfPortInterface
{
    public function __construct(
        private bool $valid = true,
    ) {
    }

    public function validate(CrudRequestInterface $request): bool
    {
        unset($request);

        return $this->valid;
    }

    public function generateToken(): string
    {
        return 'test-csrf-token';
    }
}
