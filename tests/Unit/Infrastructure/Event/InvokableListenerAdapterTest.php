<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Event;

use Bamise\Contract\Event\BeforeCreate;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Infrastructure\Event\InvokableListenerAdapter;
use Bamise\Infrastructure\Event\ListenerRegistry;
use Bamise\Infrastructure\Event\SyncEventDispatcher;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class InvokableListenerAdapterTest extends TestCase
{
    public function test_adapts_invokable_object_as_listener(): void
    {
        $listener = new RecordingInvokableListener();
        $dispatcher = new SyncEventDispatcher(new ListenerRegistry());
        $dispatcher->subscribe(BeforeCreate::class, new InvokableListenerAdapter($listener));

        $dispatcher->dispatch(new BeforeCreate(
            new CrudContext(
                OperationType::Create,
                'users',
                [],
                null,
                new FakeCrudRequest('POST', '/users'),
            ),
        ));

        self::assertSame(1, $listener->count);
    }
}

final class RecordingInvokableListener
{
    public int $count = 0;

    public function __invoke(object $event): void
    {
        unset($event);
        ++$this->count;
    }
}
