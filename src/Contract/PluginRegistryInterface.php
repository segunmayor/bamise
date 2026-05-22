<?php

declare(strict_types=1);

namespace Bamise\Contract;

use Bamise\Contract\Enum\OperationType;

interface PluginRegistryInterface
{
    public function addMiddleware(MiddlewareInterface $middleware): void;

    /**
     * @param array<string, mixed> $rules
     */
    public function addRule(string $resource, OperationType $operation, array $rules): void;

    /**
     * @param class-string $policyClass
     */
    public function addPolicy(string $policyClass): void;

    public function subscribe(string $eventClass, callable $listener, int $priority = 0): void;
}
