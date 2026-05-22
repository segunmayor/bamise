<?php

declare(strict_types=1);

namespace Bamise\Tests\Integration\Infrastructure\Security;

use Bamise\Application\Middleware\CsrfMiddleware;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Exception\CsrfException;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Infrastructure\Cache\InMemoryCache;
use Bamise\Infrastructure\Security\Csrf\CsrfConfig;
use Bamise\Infrastructure\Security\Csrf\CsrfTokenGenerator;
use Bamise\Infrastructure\Security\Csrf\SessionCsrfService;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class CsrfMiddlewareIntegrationTest extends TestCase
{
    public function test_middleware_accepts_valid_csrf_token(): void
    {
        $csrf = new SessionCsrfService(new InMemoryCache(), new CsrfTokenGenerator(), new CsrfConfig());
        $sessionId = 'integration-session';
        $token = $csrf->generateForSession($sessionId);

        $middleware = new CsrfMiddleware($csrf);
        $context = new CrudContext(
            OperationType::Create,
            'users',
            ['_session_id' => $sessionId, '_csrf' => $token],
            null,
            new FakeCrudRequest('POST', '/users', ['_session_id' => $sessionId, '_csrf' => $token]),
        );

        $result = $middleware->process($context, new TerminalHandler());

        self::assertTrue($result->success);
    }

    public function test_middleware_rejects_missing_csrf_token(): void
    {
        $csrf = new SessionCsrfService(new InMemoryCache(), new CsrfTokenGenerator(), new CsrfConfig());
        $middleware = new CsrfMiddleware($csrf);
        $context = new CrudContext(
            OperationType::Update,
            'users',
            [],
            null,
            new FakeCrudRequest('PUT', '/users/1'),
        );

        $this->expectException(CsrfException::class);
        $middleware->process($context, new TerminalHandler());
    }
}

final class TerminalHandler implements CrudHandlerInterface
{
    public function handle(CrudContext $context): CrudResult
    {
        unset($context);

        return new CrudResult(success: true, data: ['ok' => true]);
    }
}
