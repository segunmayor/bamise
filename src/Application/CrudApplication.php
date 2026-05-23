<?php

declare(strict_types=1);

namespace Bamise\Application;

use Bamise\Application\Config\ApplicationConfig;
use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Context\PipelineState;
use Bamise\Application\DTO\ResponseEnvelope;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Response\ExceptionMapper;
use Bamise\Application\Response\ResponseMapper;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\ResponseMode;
use Bamise\Contract\Http\CrudRequestInterface;
use Bamise\Contract\ValueObject\RouteOperationConfig;
use Bamise\Domain\Model\Resource;
use Bamise\Domain\Service\OperationResolver;
use Throwable;

final class CrudApplication
{
    public function __construct(
        private readonly ResourceRegistry $resourceRegistry,
        private readonly CrudContextFactory $contextFactory,
        private readonly OperationResolver $operationResolver,
        private readonly CrudHandlerInterface $pipeline,
        private readonly ResponseMapper $responseMapper,
        private readonly ExceptionMapper $exceptionMapper,
        private readonly ApplicationConfig $config = new ApplicationConfig(),
    ) {
    }

    public function handle(
        CrudRequestInterface $request,
        string $resourceName,
        ?ResponseMode $mode = null,
        ?RouteOperationConfig $routeConfig = null,
    ): ResponseEnvelope {
        try {
            $definition = $this->resourceRegistry->get($resourceName);
            $resource = Resource::fromDefinition(
                $resourceName,
                $definition->table(),
                $definition->primaryKey(),
            );
            $resolved = $this->operationResolver->resolve($request, $resource, null, $routeConfig);
            $context = $this->contextFactory->create($resolved, $request);
            $state = new PipelineState($context, $resolved, $definition);
            $result = $this->pipeline->handle($this->contextFactory->fromState($state));

            return $this->responseMapper->map(
                $result,
                $mode ?? $this->config->defaultResponseMode,
            );
        } catch (Throwable $throwable) {
            return $this->exceptionMapper->map($throwable);
        }
    }
}
