<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Middleware;

use Bamise\Application\Middleware\PipelineBuilder;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class PipelineBuilderTest extends TestCase
{
    /** @var list<string> */
    public static array $order = [];

    protected function setUp(): void
    {
        self::$order = [];
    }

    private function context(): CrudContext
    {
        return new CrudContext(OperationType::Read, 'items', [], null, new FakeCrudRequest());
    }

    private function terminal(): CrudHandlerInterface
    {
        return new class implements CrudHandlerInterface {
            public function handle(CrudContext $context): CrudResult
            {
                unset($context);
                PipelineBuilderTest::$order[] = 'terminal';

                return new CrudResult(success: true);
            }
        };
    }

    private function recordingMiddleware(string $name): MiddlewareInterface
    {
        return new class ($name) implements MiddlewareInterface {
            public function __construct(private readonly string $name)
            {
            }

            public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
            {
                PipelineBuilderTest::$order[] = $this->name;

                return $next->handle($context);
            }
        };
    }

    public function test_empty_builder_produces_pipeline_that_calls_terminal(): void
    {
        $pipeline = (new PipelineBuilder())->build($this->terminal());

        $result = $pipeline->handle($this->context());

        self::assertTrue($result->success);
        self::assertSame(['terminal'], self::$order);
    }

    public function test_single_middleware_executes_before_terminal(): void
    {
        $pipeline = (new PipelineBuilder())
            ->add($this->recordingMiddleware('only'), 10)
            ->build($this->terminal());

        $pipeline->handle($this->context());

        self::assertSame(['only', 'terminal'], self::$order);
    }

    public function test_middlewares_are_ordered_by_ascending_priority(): void
    {
        $pipeline = (new PipelineBuilder())
            ->add($this->recordingMiddleware('third'), 300)
            ->add($this->recordingMiddleware('first'), 100)
            ->add($this->recordingMiddleware('second'), 200)
            ->build($this->terminal());

        $pipeline->handle($this->context());

        self::assertSame(['first', 'second', 'third', 'terminal'], self::$order);
    }

    public function test_add_returns_same_builder_instance(): void
    {
        $builder = new PipelineBuilder();
        $returned = $builder->add($this->recordingMiddleware('a'), 0);

        self::assertSame($builder, $returned);
    }

    public function test_builder_produces_independent_pipelines_on_repeated_build(): void
    {
        $builder = (new PipelineBuilder())->add($this->recordingMiddleware('mw'), 0);

        $p1 = $builder->build($this->terminal());
        $p2 = $builder->build($this->terminal());

        self::assertNotSame($p1, $p2);

        $p1->handle($this->context());
        self::assertSame(['mw', 'terminal'], self::$order);
    }
}
