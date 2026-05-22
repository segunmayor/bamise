<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Middleware;

use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Middleware\ValidateMiddleware;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Exception\ValidationException;
use Bamise\Contract\ValidatorPortInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Contract\ValueObject\ValidationResult;
use Bamise\Domain\Service\FillableGuard;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakeResourceDefinition;
use Bamise\Tests\Fixtures\FakeValidatorPort;
use PHPUnit\Framework\TestCase;

final class ValidateMiddlewareTest extends TestCase
{
    public function test_passes_to_next_on_valid_input(): void
    {
        $reached = false;
        $next = new class ($reached) implements CrudHandlerInterface {
            public function __construct(private bool &$reached) {}
            public function handle(CrudContext $context): CrudResult
            {
                $this->reached = true;
                return new CrudResult(success: true);
            }
        };

        $this->middleware(valid: true)->process($this->context(['name' => 'Ada']), $next);

        self::assertTrue($reached);
    }

    public function test_throws_validation_exception_on_invalid_input(): void
    {
        $this->expectException(ValidationException::class);

        $this->middleware(valid: false)->process(
            $this->context(['name' => '']),
            new class implements CrudHandlerInterface {
                public function handle(CrudContext $context): CrudResult { return new CrudResult(success: true); }
            },
        );
    }

    public function test_uses_sanitized_data_from_validator_result(): void
    {
        $validator = new class implements ValidatorPortInterface {
            public function validate(array $data, array $rules): ValidationResult
            {
                return new ValidationResult(
                    valid: true,
                    errors: [],
                    sanitizedData: ['name' => 'Sanitized'],
                );
            }
        };

        $capturedContext = null;
        $next = new class ($capturedContext) implements CrudHandlerInterface {
            public function __construct(private mixed &$capturedContext) {}
            public function handle(CrudContext $context): CrudResult
            {
                $this->capturedContext = $context;
                return new CrudResult(success: true);
            }
        };

        $middleware = new ValidateMiddleware(
            $validator,
            new ResourceRegistry(['users' => new FakeResourceDefinition()]),
            new FillableGuard(),
            new CrudContextFactory(),
        );

        $middleware->process($this->context(['name' => 'Ada']), $next);

        self::assertSame(['name' => 'Sanitized'], $capturedContext->inputData);
    }

    private function middleware(bool $valid): ValidateMiddleware
    {
        return new ValidateMiddleware(
            new FakeValidatorPort(valid: $valid),
            new ResourceRegistry(['users' => new FakeResourceDefinition()]),
            new FillableGuard(),
            new CrudContextFactory(),
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    private function context(array $input): CrudContext
    {
        return new CrudContext(
            OperationType::Create,
            'users',
            $input,
            null,
            new FakeCrudRequest('POST', '/users', $input),
        );
    }
}
