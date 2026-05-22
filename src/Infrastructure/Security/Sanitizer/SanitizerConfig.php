<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\Sanitizer;

readonly class SanitizerConfig
{
    /**
     * @param list<string> $allowedTags Empty list strips all tags.
     */
    public function __construct(
        public array $allowedTags = [],
        public bool $encodeEntities = true,
    ) {
    }
}
