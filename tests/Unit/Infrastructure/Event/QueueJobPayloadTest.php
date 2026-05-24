<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Event;

use Bamise\Infrastructure\Event\QueueJobPayload;
use PHPUnit\Framework\TestCase;

final class QueueJobPayloadTest extends TestCase
{
    public function test_constructor_stores_properties(): void
    {
        $payload = new QueueJobPayload('my.job', ['key' => 'value']);

        self::assertSame('my.job', $payload->job);
        self::assertSame(['key' => 'value'], $payload->payload);
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $payload = new QueueJobPayload('bamise.event', ['op' => 'create']);

        self::assertSame(
            ['job' => 'bamise.event', 'payload' => ['op' => 'create']],
            $payload->toArray(),
        );
    }

    public function test_to_array_with_empty_payload(): void
    {
        $p = new QueueJobPayload('j', []);

        self::assertSame(['job' => 'j', 'payload' => []], $p->toArray());
    }

    public function test_to_array_with_nested_payload(): void
    {
        $data = ['a' => ['b' => ['c' => 42]]];
        $p = new QueueJobPayload('nested', $data);

        self::assertSame($data, $p->toArray()['payload']);
    }
}
