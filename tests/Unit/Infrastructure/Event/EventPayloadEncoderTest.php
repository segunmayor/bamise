<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Event;

use Bamise\Contract\Event\AfterCreate;
use Bamise\Contract\Event\DomainEventInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Infrastructure\Event\EventPayloadEncoder;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EventPayloadEncoderTest extends TestCase
{
    private EventPayloadEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new EventPayloadEncoder();
    }

    public function test_encodes_domain_event_to_expected_structure(): void
    {
        $event = new AfterCreate(
            new CrudContext(
                OperationType::Create,
                'users',
                ['name' => 'Ada'],
                null,
                new FakeCrudRequest('POST', '/users'),
            ),
            ['id' => 7],
        );

        $encoded = $this->encoder->encode($event);

        self::assertSame(AfterCreate::class, $encoded['event_class']);
        self::assertSame(OperationType::Create->value, $encoded['operation']);
        self::assertSame('users', $encoded['resource_name']);
        self::assertSame(['name' => 'Ada'], $encoded['input_data']);
        self::assertSame(['id' => 7], $encoded['payload']);
    }

    public function test_encode_includes_null_payload_when_event_payload_is_null(): void
    {
        $event = new AfterCreate(
            new CrudContext(
                OperationType::Create,
                'users',
                [],
                null,
                new FakeCrudRequest(),
            ),
            null,
        );

        $encoded = $this->encoder->encode($event);

        self::assertNull($encoded['payload']);
    }

    public function test_encode_throws_for_non_domain_event(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->encoder->encode(new \stdClass());
    }

    public function test_encode_throws_for_domain_event_without_crud_context(): void
    {
        $event = new class implements DomainEventInterface {
        };

        $this->expectException(InvalidArgumentException::class);

        $this->encoder->encode($event);
    }

    public function test_encode_throws_for_domain_event_with_non_context_property(): void
    {
        $event = new class (context: 'not-a-context') implements DomainEventInterface {
            public function __construct(public readonly string $context)
            {
            }
        };

        $this->expectException(InvalidArgumentException::class);

        $this->encoder->encode($event);
    }

    public function test_payload_is_null_when_event_has_no_payload_property(): void
    {
        $ctx = new CrudContext(
            OperationType::Create,
            'posts',
            [],
            null,
            new FakeCrudRequest(),
        );

        $event = new class ($ctx) implements DomainEventInterface {
            public function __construct(public readonly CrudContext $context)
            {
            }
        };

        $encoded = $this->encoder->encode($event);

        self::assertNull($encoded['payload']);
    }

    public function test_payload_is_null_when_event_payload_property_is_not_array(): void
    {
        $ctx = new CrudContext(
            OperationType::Create,
            'posts',
            [],
            null,
            new FakeCrudRequest(),
        );

        $event = new class ($ctx) implements DomainEventInterface {
            public readonly string $payload;

            public function __construct(public readonly CrudContext $context)
            {
                $this->payload = 'not-an-array';
            }
        };

        $encoded = $this->encoder->encode($event);

        self::assertNull($encoded['payload']);
    }
}
