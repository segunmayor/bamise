<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Middleware;

use Bamise\Application\Middleware\SigningMiddleware;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Exception\AuthorizationException;
use Bamise\Contract\Http\CrudRequestInterface;
use Bamise\Contract\Security\RequestSignerPortInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class SigningMiddlewareTest extends TestCase
{
    private CrudHandlerInterface $next;

    protected function setUp(): void
    {
        $this->next = new class implements CrudHandlerInterface {
            public function handle(CrudContext $context): CrudResult
            {
                unset($context);

                return new CrudResult(success: true);
            }
        };
    }

    public function test_throws_authorization_exception_when_signature_is_invalid(): void
    {
        $signer = new class implements RequestSignerPortInterface {
            public function verify(CrudRequestInterface $request): bool
            {
                unset($request);

                return false;
            }

            public function sign(array $payload): string
            {
                unset($payload);

                return '';
            }
        };
        $context = new CrudContext(OperationType::Create, 'posts', [], null, new FakeCrudRequest());
        $middleware = new SigningMiddleware($signer);

        $this->expectException(AuthorizationException::class);
        $middleware->process($context, $this->next);
    }

    public function test_proceeds_to_next_when_signature_is_valid(): void
    {
        $signer = new class implements RequestSignerPortInterface {
            public function verify(CrudRequestInterface $request): bool
            {
                unset($request);

                return true;
            }

            public function sign(array $payload): string
            {
                unset($payload);

                return 'sig';
            }
        };
        $context = new CrudContext(OperationType::Read, 'posts', [], null, new FakeCrudRequest());
        $middleware = new SigningMiddleware($signer);

        $result = $middleware->process($context, $this->next);

        self::assertTrue($result->success);
    }

    public function test_exception_message_references_signature(): void
    {
        $signer = new class implements RequestSignerPortInterface {
            public function verify(CrudRequestInterface $request): bool
            {
                unset($request);

                return false;
            }

            public function sign(array $payload): string
            {
                unset($payload);

                return '';
            }
        };
        $context = new CrudContext(OperationType::Create, 'orders', [], null, new FakeCrudRequest());
        $middleware = new SigningMiddleware($signer);

        try {
            $middleware->process($context, $this->next);
            self::fail('Expected AuthorizationException');
        } catch (AuthorizationException $exception) {
            self::assertStringContainsStringIgnoringCase('signature', $exception->getMessage());
        }
    }
}
