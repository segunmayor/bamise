<?php

declare(strict_types=1);

namespace Bamise\Application\Middleware;

use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;

final class MiddlewarePipeline implements CrudHandlerInterface
{
    /** @var list<PrioritizedMiddleware> */
    private array $middleware;

    /**
     * @param iterable<PrioritizedMiddleware|MiddlewareInterface> $middleware
     */
    public function __construct(
        iterable $middleware,
        private readonly CrudHandlerInterface $terminal,
    ) {
        $this->middleware = $this->normalize($middleware);
        usort(
            $this->middleware,
            static fn (PrioritizedMiddleware $a, PrioritizedMiddleware $b): int => $a->priority <=> $b->priority,
        );
    }

    public function handle(CrudContext $context): CrudResult
    {
        $handler = $this->terminal;

        foreach (array_reverse($this->middleware) as $entry) {
            $handler = new DelegateHandler($entry->middleware, $handler);
        }

        return $handler->handle($context);
    }

    /**
     * @param iterable<PrioritizedMiddleware|MiddlewareInterface> $middleware
     *
     * @return list<PrioritizedMiddleware>
     */
    private function normalize(iterable $middleware): array
    {
        $normalized = [];

        foreach ($middleware as $index => $item) {
            if ($item instanceof PrioritizedMiddleware) {
                $normalized[] = $item;
                continue;
            }

            $normalized[] = new PrioritizedMiddleware($item, (int) $index * 100);
        }

        return $normalized;
    }
}
