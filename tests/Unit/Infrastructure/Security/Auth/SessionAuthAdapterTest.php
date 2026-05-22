<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security\Auth;

use Bamise\Application\Context\AuthSubjectDto;
use Bamise\Infrastructure\Security\Auth\SessionAuthAdapter;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class SessionAuthAdapterTest extends TestCase
{
    public function test_returns_null_when_subject_field_absent(): void
    {
        $adapter = new SessionAuthAdapter();

        self::assertNull($adapter->authenticate(new FakeCrudRequest()));
    }

    public function test_returns_null_when_subject_field_is_empty(): void
    {
        $adapter = new SessionAuthAdapter();

        self::assertNull($adapter->authenticate(new FakeCrudRequest(input: ['_subject_id' => ''])));
    }

    public function test_maps_numeric_string_to_int_id(): void
    {
        $adapter = new SessionAuthAdapter();
        $result = $adapter->authenticate(new FakeCrudRequest(input: ['_subject_id' => '42']));

        self::assertInstanceOf(AuthSubjectDto::class, $result);
        self::assertSame(42, $result->id);
    }

    public function test_maps_string_id(): void
    {
        $adapter = new SessionAuthAdapter();
        $result = $adapter->authenticate(new FakeCrudRequest(input: ['_subject_id' => 'user-xyz']));

        self::assertInstanceOf(AuthSubjectDto::class, $result);
        self::assertSame('user-xyz', $result->id);
    }

    public function test_subject_always_returns_null(): void
    {
        self::assertNull((new SessionAuthAdapter())->subject());
    }

    public function test_custom_field_name_is_used(): void
    {
        $adapter = new SessionAuthAdapter(subjectField: 'user_id');
        $result = $adapter->authenticate(new FakeCrudRequest(input: ['user_id' => 99]));

        self::assertInstanceOf(AuthSubjectDto::class, $result);
        self::assertSame(99, $result->id);
    }
}
