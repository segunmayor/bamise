<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Queue;

use Bamise\Infrastructure\Queue\InMemoryQueue;
use PHPUnit\Framework\TestCase;

final class InMemoryQueueTest extends TestCase
{
    private InMemoryQueue $queue;

    protected function setUp(): void
    {
        $this->queue = new InMemoryQueue();
    }

    public function test_starts_empty(): void
    {
        self::assertCount(0, $this->queue->all());
        self::assertSame(0, $this->queue->count());
    }

    public function test_push_adds_a_job(): void
    {
        $this->queue->push('my.job', ['key' => 'value']);

        self::assertSame(1, $this->queue->count());
    }

    public function test_all_returns_pushed_jobs_in_order(): void
    {
        $this->queue->push('job.one', ['a' => 1]);
        $this->queue->push('job.two', ['b' => 2]);

        $jobs = $this->queue->all();

        self::assertCount(2, $jobs);
        self::assertSame('job.one', $jobs[0]['job']);
        self::assertSame(['a' => 1], $jobs[0]['payload']);
        self::assertSame('job.two', $jobs[1]['job']);
        self::assertSame(['b' => 2], $jobs[1]['payload']);
    }

    public function test_clear_removes_all_jobs(): void
    {
        $this->queue->push('job', []);
        $this->queue->push('job', []);
        $this->queue->clear();

        self::assertSame(0, $this->queue->count());
        self::assertSame([], $this->queue->all());
    }

    public function test_push_with_empty_payload(): void
    {
        $this->queue->push('empty.job', []);

        self::assertSame([], $this->queue->all()[0]['payload']);
    }

    public function test_push_preserves_nested_payload(): void
    {
        $payload = ['nested' => ['deep' => true], 'list' => [1, 2, 3]];
        $this->queue->push('complex.job', $payload);

        self::assertSame($payload, $this->queue->all()[0]['payload']);
    }

    public function test_count_matches_number_of_pushes(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->queue->push('j', []);
        }

        self::assertSame(5, $this->queue->count());
    }
}
