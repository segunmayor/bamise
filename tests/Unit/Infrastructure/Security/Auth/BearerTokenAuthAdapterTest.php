<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security\Auth;

use Bamise\Application\Context\AuthSubjectDto;
use Bamise\Infrastructure\Security\Auth\BearerTokenAuthAdapter;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class BearerTokenAuthAdapterTest extends TestCase
{
    public function test_returns_null_when_no_authorization_header(): void
    {
        $adapter = new BearerTokenAuthAdapter();

        self::assertNull($adapter->authenticate(new FakeCrudRequest()));
    }

    public function test_returns_null_when_token_is_not_bearer(): void
    {
        $adapter = new BearerTokenAuthAdapter();

        self::assertNull($adapter->authenticate(new FakeCrudRequest(headers: ['Authorization' => 'Basic abc'])));
    }

    public function test_parses_numeric_id_as_int(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $result = $adapter->authenticate(new FakeCrudRequest(headers: ['Authorization' => 'Bearer 42']));

        self::assertInstanceOf(AuthSubjectDto::class, $result);
        self::assertSame(42, $result->id);
    }

    public function test_parses_string_id(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $result = $adapter->authenticate(new FakeCrudRequest(headers: ['Authorization' => 'Bearer user-abc']));

        self::assertInstanceOf(AuthSubjectDto::class, $result);
        self::assertSame('user-abc', $result->id);
    }

    public function test_parses_roles_and_permissions_from_pipe_delimited_token(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $result = $adapter->authenticate(
            new FakeCrudRequest(headers: ['Authorization' => 'Bearer 1|admin,editor|users.create,users.read']),
        );

        self::assertInstanceOf(AuthSubjectDto::class, $result);
        self::assertSame(['admin', 'editor'], $result->roles);
        self::assertSame(['users.create', 'users.read'], $result->permissions);
    }

    public function test_subject_stored_after_authenticate(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $adapter->authenticate(new FakeCrudRequest(headers: ['Authorization' => 'Bearer 7']));

        self::assertNotNull($adapter->subject());
    }

    public function test_subject_returns_null_before_authenticate(): void
    {
        self::assertNull((new BearerTokenAuthAdapter())->subject());
    }

    public function test_case_insensitive_bearer_prefix(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $result = $adapter->authenticate(new FakeCrudRequest(headers: ['Authorization' => 'bearer 10']));

        self::assertInstanceOf(AuthSubjectDto::class, $result);
        self::assertSame(10, $result->id);
    }

    public function test_authorization_header_key_is_case_insensitive(): void
    {
        $adapter = new BearerTokenAuthAdapter();
        $result = $adapter->authenticate(new FakeCrudRequest(headers: ['authorization' => 'Bearer 5']));

        self::assertInstanceOf(AuthSubjectDto::class, $result);
    }
}
