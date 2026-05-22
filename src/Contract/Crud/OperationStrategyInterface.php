<?php

declare(strict_types=1);

namespace Bamise\Contract\Crud;

use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;

interface OperationStrategyInterface
{
    public function execute(CrudContext $context): CrudResult;
}
