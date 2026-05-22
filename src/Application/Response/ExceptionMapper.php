<?php

declare(strict_types=1);

namespace Bamise\Application\Response;

use Bamise\Application\DTO\ResponseEnvelope;
use Bamise\Contract\Exception\AuthorizationException;
use Bamise\Contract\Exception\BamiseException;
use Bamise\Contract\Exception\CsrfException;
use Bamise\Contract\Exception\OperationResolutionException;
use Bamise\Contract\Exception\RateLimitException;
use Bamise\Contract\Exception\ValidationException;
use Bamise\Domain\Exception\InsufficientPermissionException;
use Bamise\Domain\Exception\MassAssignmentException;
use Throwable;

final class ExceptionMapper
{
    public function map(Throwable $throwable): ResponseEnvelope
    {
        $httpStatus = match (true) {
            $throwable instanceof InsufficientPermissionException,
            $throwable instanceof AuthorizationException => 403,
            $throwable instanceof CsrfException => 403,
            $throwable instanceof RateLimitException => 429,
            $throwable instanceof ValidationException,
            $throwable instanceof MassAssignmentException => 422,
            $throwable instanceof OperationResolutionException => 400,
            $throwable instanceof BamiseException => 400,
            default => 500,
        };

        return new ResponseEnvelope(
            success: false,
            errors: [
                'message' => $throwable->getMessage(),
                'type' => $throwable::class,
            ],
            httpStatus: $httpStatus,
        );
    }
}
