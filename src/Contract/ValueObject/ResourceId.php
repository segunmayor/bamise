<?php

declare(strict_types=1);

namespace Bamise\Contract\ValueObject;

readonly class ResourceId
{
    public function __construct(
        public string|int $value,
    ) {
    }
}
