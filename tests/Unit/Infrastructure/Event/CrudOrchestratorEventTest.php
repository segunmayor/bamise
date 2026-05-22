<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Event;

use Bamise\Application\Handler\CrudOrchestrator;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Event\AfterCreate;
use Bamise\Contract\Event\BeforeCreate;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Domain\Event\LifecycleEventFactory;
use Bamise\Infrastructure\Event\ListenerRegistry;
use Bamise\Infrastructure\Event\SyncEventDispatcher;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class CrudOrchestratorEventTest extends TestCase
{
    public function test_dispatches_before_and_after_create_on_success(): void
    {
        $dispatched = [];
        $registry = new ListenerRegistry();
        $dispatcher = new SyncEventDispatcher($registry);
        $dispatcher->subscribe(BeforeCreate::class, static function (object $event) use (&$dispatched): void {
            $dispatched[] = $event::class;
        });
        $dispatcher->subscribe(AfterCreate::class, static function (object $event) use (&$dispatched): void {
            $dispatched[] = $event::class;
        });

        $orchestrator = new CrudOrchestrator(
            $dispatcher,
            new LifecycleEventFactory(),
            new StubCreateHandler(),
        );

        $context = new CrudContext(
            OperationType::Create,
            'users',
            ['name' => 'Ada'],
            null,
            new FakeCrudRequest('POST', '/users', ['name' => 'Ada']),
        );

        $result = $orchestrator->handle($context);

        self::assertTrue($result->success);
        self::assertSame([BeforeCreate::class, AfterCreate::class], $dispatched);
    }

    public function test_skips_after_event_when_inner_handler_fails(): void
    {
        $dispatched = [];
        $dispatcher = new SyncEventDispatcher(new ListenerRegistry());
        $dispatcher->subscribe(BeforeCreate::class, static function (object $event) use (&$dispatched): void {
            $dispatched[] = $event::class;
        });
        $dispatcher->subscribe(AfterCreate::class, static function (object $event) use (&$dispatched): void {
            $dispatched[] = $event::class;
        });

        $orchestrator = new CrudOrchestrator(
            $dispatcher,
            new LifecycleEventFactory(),
            new StubCreateHandler(success: false),
        );

        $context = new CrudContext(
            OperationType::Create,
            'users',
            [],
            null,
            new FakeCrudRequest('POST', '/users'),
        );

        $orchestrator->handle($context);

        self::assertSame([BeforeCreate::class], $dispatched);
    }
}

final class StubCreateHandler implements CrudHandlerInterface
{
    public function __construct(
        private readonly bool $success = true,
    ) {
    }

    public function handle(CrudContext $context): CrudResult
    {
        unset($context);

        return new CrudResult(success: $this->success, data: ['id' => 1]);
    }
}
