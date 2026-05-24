<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Plugin;

use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\EventDispatcherPortInterface;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\PluginRegistryInterface;

final class DefaultPluginRegistry implements PluginRegistryInterface
{
    /** @var list<MiddlewareInterface> */
    private array $middleware = [];

    /** @var list<class-string> */
    private array $policies = [];

    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $rules = [];

    public function __construct(
        private readonly EventDispatcherPortInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * @param array<string, mixed> $rules
     */
    #[\Override]
    public function addRule(string $resource, OperationType $operation, array $rules): void
    {
        $this->rules[$resource][$operation->value] = $rules;
    }

    /**
     * @param class-string $policyClass
     */
    #[\Override]
    public function addPolicy(string $policyClass): void
    {
        $this->policies[] = $policyClass;
    }

    #[\Override]
    public function subscribe(string $eventClass, callable $listener, int $priority = 0): void
    {
        $this->eventDispatcher->subscribe($eventClass, $listener, $priority);
    }

    /**
     * @return list<MiddlewareInterface>
     */
    public function middleware(): array
    {
        return $this->middleware;
    }

    /**
     * @return list<class-string>
     */
    public function policies(): array
    {
        return $this->policies;
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function rules(): array
    {
        return $this->rules;
    }
}
