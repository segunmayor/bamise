<?php

declare(strict_types=1);

namespace Bamise\Contract\Crud;

use Bamise\Contract\Enum\OperationType;

interface OperationStrategyFactoryInterface
{
    public function for(OperationType $operation): OperationStrategyInterface;
}
