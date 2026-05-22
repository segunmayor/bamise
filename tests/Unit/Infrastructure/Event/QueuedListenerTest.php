<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Event;

use Bamise\Contract\Event\AfterCreate;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Infrastructure\Event\AsyncListenerRegistrar;
use Bamise\Infrastructure\Event\ListenerRegistry;
use Bamise\Infrastructure\Event\SyncEventDispatcher;
use Bamise\Infrastructure\Queue\InMemoryQueue;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class QueuedListenerTest extends TestCase
{
    public function test_async_listener_pushes_job_to_in_memory_queue(): void
    {
        $queue = new InMemoryQueue();
        $dispatcher = new SyncEventDispatcher(new ListenerRegistry(), $queue);
        $registrar = new AsyncListenerRegistrar($dispatcher);
        $called = false;
        $registrar->subscribe(AfterCreate::class, static function () use (&$called): void {
            $called = true;
        });

        $event = new AfterCreate(
            new CrudContext(
                OperationType::Create,
                'users',
                ['name' => 'Ada'],
                null,
                new FakeCrudRequest('POST', '/users'),
            ),
            ['id' => 1],
        );

        $dispatcher->dispatch($event);

        self::assertFalse($called);
        self::assertSame(1, $queue->count());
        self::assertSame(SyncEventDispatcher::ASYNC_JOB, $queue->all()[0]['job']);
        self::assertSame(AfterCreate::class, $queue->all()[0]['payload']['event_class']);
        self::assertSame('users', $queue->all()[0]['payload']['resource_name']);
        self::assertSame(['id' => 1], $queue->all()[0]['payload']['payload']);
    }
}
