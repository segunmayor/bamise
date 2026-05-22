<?php

declare(strict_types=1);

namespace Bamise\Tests\Fixtures;

use Bamise\Contract\AuthPortInterface;
use Bamise\Contract\Http\CrudRequestInterface;
use Bamise\Domain\Model\Subject;

final class FakeAuthPort implements AuthPortInterface
{
    public function __construct(
        private ?Subject $subject = null,
    ) {
    }

    public function subject(): ?object
    {
        return $this->subject;
    }

    public function authenticate(CrudRequestInterface $request): ?object
    {
        unset($request);

        return $this->subject;
    }
}
