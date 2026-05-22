<?php

declare(strict_types=1);

namespace Bamise\Application\Config;

use Bamise\Contract\Enum\ResponseMode;

readonly class ApplicationConfig
{
    public function __construct(
        public ResponseMode $defaultResponseMode = ResponseMode::Api,
    ) {
    }
}
