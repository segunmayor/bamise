<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Infrastructure\Cache\InMemoryCache;
use Bamise\Infrastructure\Security\Csrf\CsrfConfig;
use Bamise\Infrastructure\Security\Csrf\CsrfTokenGenerator;
use Bamise\Infrastructure\Security\Csrf\SessionCsrfService;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class CsrfServiceTest extends TestCase
{
    public function test_valid_token_passes_validation(): void
    {
        $service = $this->service();
        $sessionId = 'sess-1';
        $token = $service->generateForSession($sessionId);

        $request = new FakeCrudRequest(
            'POST',
            '/users',
            [
                '_session_id' => $sessionId,
                '_csrf' => $token,
            ],
        );

        self::assertTrue($service->validate($request));
    }

    public function test_token_is_consumed_after_validation_preventing_replay(): void
    {
        $service = $this->service();
        $sessionId = 'sess-replay';
        $token = $service->generateForSession($sessionId);

        $request = new FakeCrudRequest(
            'POST',
            '/users',
            [
                '_session_id' => $sessionId,
                '_csrf' => $token,
            ],
        );

        self::assertTrue($service->validate($request));
        self::assertFalse($service->validate($request));
    }

    public function test_invalid_token_fails_validation(): void
    {
        $service = $this->service();
        $sessionId = 'sess-2';
        $service->generateForSession($sessionId);

        $request = new FakeCrudRequest(
            'POST',
            '/users',
            [
                '_session_id' => $sessionId,
                '_csrf' => 'wrong-token',
            ],
        );

        self::assertFalse($service->validate($request));
    }

    public function test_expired_token_fails_validation(): void
    {
        $cache = new InMemoryCache();
        $config = new CsrfConfig(ttlSeconds: 1);
        $service = new SessionCsrfService($cache, new CsrfTokenGenerator(), $config);
        $sessionId = 'sess-3';
        $token = $service->generateForSession($sessionId);
        $cache->delete('csrf:' . $sessionId);

        $request = new FakeCrudRequest(
            'POST',
            '/users',
            [
                '_session_id' => $sessionId,
                '_csrf' => $token,
            ],
        );

        self::assertFalse($service->validate($request));
    }

    private function service(): SessionCsrfService
    {
        return new SessionCsrfService(
            new InMemoryCache(),
            new CsrfTokenGenerator(),
            new CsrfConfig(),
        );
    }
}
