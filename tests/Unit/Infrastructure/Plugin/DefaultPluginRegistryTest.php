<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Plugin;

use Bamise\Contract\Event\BeforeCreate;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Infrastructure\Event\ListenerRegistry;
use Bamise\Infrastructure\Event\SyncEventDispatcher;
use Bamise\Infrastructure\Plugin\DefaultPluginRegistry;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class DefaultPluginRegistryTest extends TestCase
{
    public function test_subscribe_delegates_to_event_dispatcher(): void
    {
        $called = false;
        $dispatcher = new SyncEventDispatcher(new ListenerRegistry());
        $registry = new DefaultPluginRegistry($dispatcher);
        $registry->subscribe(BeforeCreate::class, static function () use (&$called): void {
            $called = true;
        });

        $dispatcher->dispatch(new BeforeCreate(
            new CrudContext(
                OperationType::Create,
                'users',
                [],
                null,
                new FakeCrudRequest('POST', '/users'),
            ),
        ));

        self::assertTrue($called);
    }

    public function test_stores_middleware_rules_and_policies(): void
    {
        $registry = new DefaultPluginRegistry(new SyncEventDispatcher(new ListenerRegistry()));
        $registry->addRule('users', OperationType::Create, ['name' => 'required']);
        $registry->addPolicy('App\\Policy\\UserPolicy');

        self::assertSame(['name' => 'required'], $registry->rules()['users']['create']);
        self::assertContains('App\\Policy\\UserPolicy', $registry->policies());
    }
}
