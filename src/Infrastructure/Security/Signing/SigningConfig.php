<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\Signing;

readonly class SigningConfig
{
    public function __construct(
        public string $secret,
        public string $timestampHeader = 'X-Bamise-Timestamp',
        public string $nonceHeader = 'X-Bamise-Nonce',
        public string $signatureHeader = 'X-Bamise-Signature',
        public int $maxSkewSeconds = 300,
        public int $nonceTtlSeconds = 600,
    ) {
    }
}
