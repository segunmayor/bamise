<?php

declare(strict_types=1);

namespace Bamise\Contract;

use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;

interface MiddlewareInterface
{
    public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult;
}
