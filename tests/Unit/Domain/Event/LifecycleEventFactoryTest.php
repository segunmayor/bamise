<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Domain\Event;

use Bamise\Contract\Event\AfterCreate;
use Bamise\Contract\Event\AfterDelete;
use Bamise\Contract\Event\AfterUpdate;
use Bamise\Contract\Event\BeforeCreate;
use Bamise\Contract\Event\BeforeDelete;
use Bamise\Contract\Event\BeforeUpdate;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Domain\Event\LifecycleEventFactory;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LifecycleEventFactoryTest extends TestCase
{
    private LifecycleEventFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new LifecycleEventFactory();
    }

    // ── before() ─────────────────────────────────────────────────────────────

    public function test_before_create_returns_before_create(): void
    {
        $event = $this->factory->before($this->context(OperationType::Create));

        self::assertInstanceOf(BeforeCreate::class, $event);
    }

    public function test_before_update_returns_before_update(): void
    {
        $event = $this->factory->before($this->context(OperationType::Update));

        self::assertInstanceOf(BeforeUpdate::class, $event);
    }

    public function test_before_bulk_update_returns_before_update(): void
    {
        $event = $this->factory->before($this->context(OperationType::BulkUpdate));

        self::assertInstanceOf(BeforeUpdate::class, $event);
    }

    public function test_before_delete_returns_before_delete(): void
    {
        $event = $this->factory->before($this->context(OperationType::Delete));

        self::assertInstanceOf(BeforeDelete::class, $event);
    }

    public function test_before_bulk_delete_returns_before_delete(): void
    {
        $event = $this->factory->before($this->context(OperationType::BulkDelete));

        self::assertInstanceOf(BeforeDelete::class, $event);
    }

    public function test_before_read_throws_invalid_argument(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->factory->before($this->context(OperationType::Read));
    }

    // ── after() ──────────────────────────────────────────────────────────────

    public function test_after_create_returns_after_create(): void
    {
        $event = $this->factory->after($this->context(OperationType::Create));

        self::assertInstanceOf(AfterCreate::class, $event);
    }

    public function test_after_update_returns_after_update(): void
    {
        $event = $this->factory->after($this->context(OperationType::Update));

        self::assertInstanceOf(AfterUpdate::class, $event);
    }

    public function test_after_bulk_update_returns_after_update(): void
    {
        $event = $this->factory->after($this->context(OperationType::BulkUpdate));

        self::assertInstanceOf(AfterUpdate::class, $event);
    }

    public function test_after_delete_returns_after_delete(): void
    {
        $event = $this->factory->after($this->context(OperationType::Delete));

        self::assertInstanceOf(AfterDelete::class, $event);
    }

    public function test_after_bulk_delete_returns_after_delete(): void
    {
        $event = $this->factory->after($this->context(OperationType::BulkDelete));

        self::assertInstanceOf(AfterDelete::class, $event);
    }

    public function test_after_read_throws_invalid_argument(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->factory->after($this->context(OperationType::Read));
    }

    // ── Named helpers ─────────────────────────────────────────────────────────

    public function test_before_create_helper_returns_typed_instance(): void
    {
        $ctx = $this->context(OperationType::Create);

        self::assertInstanceOf(BeforeCreate::class, $this->factory->beforeCreate($ctx));
    }

    public function test_after_create_helper_returns_typed_instance(): void
    {
        $ctx = $this->context(OperationType::Create);

        self::assertInstanceOf(AfterCreate::class, $this->factory->afterCreate($ctx));
    }

    public function test_before_update_helper_returns_typed_instance(): void
    {
        $ctx = $this->context(OperationType::Update);

        self::assertInstanceOf(BeforeUpdate::class, $this->factory->beforeUpdate($ctx));
    }

    public function test_after_update_helper_returns_typed_instance(): void
    {
        $ctx = $this->context(OperationType::Update);

        self::assertInstanceOf(AfterUpdate::class, $this->factory->afterUpdate($ctx));
    }

    public function test_before_delete_helper_returns_typed_instance(): void
    {
        $ctx = $this->context(OperationType::Delete);

        self::assertInstanceOf(BeforeDelete::class, $this->factory->beforeDelete($ctx));
    }

    public function test_after_delete_helper_returns_typed_instance(): void
    {
        $ctx = $this->context(OperationType::Delete);

        self::assertInstanceOf(AfterDelete::class, $this->factory->afterDelete($ctx));
    }

    // ── Payload pass-through ──────────────────────────────────────────────────

    public function test_before_passes_payload_through(): void
    {
        $payload = ['foo' => 'bar'];
        $ctx = $this->context(OperationType::Create);

        /** @var BeforeCreate $event */
        $event = $this->factory->before($ctx, $payload);

        self::assertSame($payload, $event->payload);
    }

    public function test_after_passes_payload_through(): void
    {
        $payload = ['id' => 42];
        $ctx = $this->context(OperationType::Create);

        /** @var AfterCreate $event */
        $event = $this->factory->after($ctx, $payload);

        self::assertSame($payload, $event->payload);
    }

    public function test_payload_defaults_to_null(): void
    {
        $ctx = $this->context(OperationType::Create);

        /** @var BeforeCreate $event */
        $event = $this->factory->before($ctx);

        self::assertNull($event->payload);
    }

    // ── Context propagation ───────────────────────────────────────────────────

    public function test_context_is_carried_on_before_event(): void
    {
        $ctx = $this->context(OperationType::Create, 'orders');

        /** @var BeforeCreate $event */
        $event = $this->factory->before($ctx);

        self::assertSame('orders', $event->context->resourceName);
    }

    public function test_context_is_carried_on_after_event(): void
    {
        $ctx = $this->context(OperationType::Create, 'orders');

        /** @var AfterCreate $event */
        $event = $this->factory->after($ctx);

        self::assertSame('orders', $event->context->resourceName);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function context(OperationType $operation, string $resource = 'users'): CrudContext
    {
        return new CrudContext(
            $operation,
            $resource,
            [],
            null,
            new FakeCrudRequest(),
        );
    }
}
