<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application;

use Bamise\Application\Response\ExceptionMapper;
use Bamise\Contract\Exception\AuthorizationException;
use Bamise\Contract\Exception\CsrfException;
use Bamise\Contract\Exception\OperationResolutionException;
use Bamise\Contract\Exception\RateLimitException;
use Bamise\Contract\Exception\ValidationException;
use Bamise\Domain\Exception\InsufficientPermissionException;
use Bamise\Domain\Exception\MassAssignmentException;
use Exception;
use PHPUnit\Framework\TestCase;

final class ExceptionMapperTest extends TestCase
{
    private ExceptionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ExceptionMapper();
    }

    /**
     * @return array<string, array{0: Exception, 1: int}>
     */
    public static function exceptionProvider(): array
    {
        return [
            'insufficient permission' => [new InsufficientPermissionException('denied'), 403],
            'authorization' => [new AuthorizationException('denied'), 403],
            'csrf' => [new CsrfException('bad token'), 403],
            'rate limit' => [new RateLimitException('slow down'), 429],
            'validation' => [new ValidationException('invalid'), 422],
            'mass assignment' => [new MassAssignmentException('field'), 422],
            'operation resolution' => [new OperationResolutionException('ambiguous'), 400],
            'generic' => [new Exception('boom'), 500],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('exceptionProvider')]
    public function test_maps_exception_to_envelope(Exception $exception, int $expectedStatus): void
    {
        $envelope = $this->mapper->map($exception);

        self::assertFalse($envelope->success);
        self::assertSame($expectedStatus, $envelope->httpStatus);
        self::assertArrayHasKey('message', $envelope->errors);
    }
}
