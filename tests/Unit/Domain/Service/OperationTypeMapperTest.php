<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Domain\Service;

use Bamise\Contract\Enum\OperationType;
use Bamise\Domain\Service\OperationTypeMapper;
use PHPUnit\Framework\TestCase;

final class OperationTypeMapperTest extends TestCase
{
    private OperationTypeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new OperationTypeMapper();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('httpMethodProvider')]
    public function test_from_http_method_maps_correctly(string $method, OperationType $expected): void
    {
        self::assertSame($expected, $this->mapper->fromHttpMethod($method));
    }

    public static function httpMethodProvider(): array
    {
        return [
            ['POST', OperationType::Create],
            ['GET', OperationType::Read],
            ['PUT', OperationType::Update],
            ['PATCH', OperationType::Update],
            ['DELETE', OperationType::Delete],
        ];
    }

    public function test_from_http_method_returns_null_for_unknown(): void
    {
        self::assertNull($this->mapper->fromHttpMethod('OPTIONS'));
    }

    public function test_from_http_method_respects_override(): void
    {
        $result = $this->mapper->fromHttpMethod('GET', OperationType::Create);

        self::assertSame(OperationType::Create, $result);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stringProvider')]
    public function test_from_string_maps_correctly(string $value, OperationType $expected): void
    {
        self::assertSame($expected, $this->mapper->fromString($value));
    }

    public static function stringProvider(): array
    {
        return [
            ['create', OperationType::Create],
            ['read', OperationType::Read],
            ['update', OperationType::Update],
            ['delete', OperationType::Delete],
            ['bulk_update', OperationType::BulkUpdate],
            ['bulk_delete', OperationType::BulkDelete],
            ['CREATE', OperationType::Create],
            [' update ', OperationType::Update],
        ];
    }

    public function test_from_string_returns_null_for_unknown(): void
    {
        self::assertNull($this->mapper->fromString('unknown_operation'));
    }
}
