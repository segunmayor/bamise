<?php

declare(strict_types=1);

namespace Bamise\Application\Middleware;

use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\MiddlewareInterface;

final class PipelineBuilder
{
    /** @var list<PrioritizedMiddleware> */
    private array $entries = [];

    public function add(MiddlewareInterface $middleware, int $priority = 0): self
    {
        $this->entries[] = new PrioritizedMiddleware($middleware, $priority);

        return $this;
    }

    public function build(CrudHandlerInterface $terminal): MiddlewarePipeline
    {
        return new MiddlewarePipeline($this->entries, $terminal);
    }
}
