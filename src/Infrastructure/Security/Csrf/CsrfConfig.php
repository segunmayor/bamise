<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\Csrf;

readonly class CsrfConfig
{
    public function __construct(
        public string $fieldName = '_csrf',
        public int $tokenLength = 32,
        public int $ttlSeconds = 3600,
        public string $sessionField = '_session_id',
        public string $defaultSessionId = 'default',
    ) {
    }
}
