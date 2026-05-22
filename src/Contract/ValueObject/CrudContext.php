<?php

declare(strict_types=1);

namespace Bamise\Contract\ValueObject;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Http\CrudRequestInterface;

readonly class CrudContext
{
    /**
     * @param array<string, mixed> $inputData
     */
    public function __construct(
        public OperationType $operation,
        public string $resourceName,
        public array $inputData,
        public ?object $subject,
        public CrudRequestInterface $request,
    ) {
    }
}
