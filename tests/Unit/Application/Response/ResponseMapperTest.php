<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Response;

use Bamise\Application\DTO\ResponseEnvelope;
use Bamise\Application\Response\ResponseMapper;
use Bamise\Contract\Enum\ResponseMode;
use Bamise\Contract\ValueObject\CrudResult;
use PHPUnit\Framework\TestCase;

final class ResponseMapperTest extends TestCase
{
    public function test_success_result_maps_to_200(): void
    {
        $result = new CrudResult(success: true, data: ['id' => 1]);

        $envelope = (new ResponseMapper())->map($result, ResponseMode::Api);

        self::assertInstanceOf(ResponseEnvelope::class, $envelope);
        self::assertTrue($envelope->success);
        self::assertSame(200, $envelope->httpStatus);
        self::assertSame(['id' => 1], $envelope->data);
    }

    public function test_failure_result_maps_to_422(): void
    {
        $result = new CrudResult(
            success: false,
            errors: ['message' => 'Validation failed'],
        );

        $envelope = (new ResponseMapper())->map($result, ResponseMode::Api);

        self::assertFalse($envelope->success);
        self::assertSame(422, $envelope->httpStatus);
        self::assertSame(['message' => 'Validation failed'], $envelope->errors);
    }

    public function test_meta_is_carried_to_envelope(): void
    {
        $result = new CrudResult(success: true, meta: ['operation' => 'create']);

        $envelope = (new ResponseMapper())->map($result, ResponseMode::Api);

        self::assertSame(['operation' => 'create'], $envelope->meta);
    }
}
