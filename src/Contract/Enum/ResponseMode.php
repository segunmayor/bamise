<?php

declare(strict_types=1);

namespace Bamise\Contract\Enum;

enum ResponseMode: string
{
    case Web = 'web';
    case Api = 'api';
}
