<?php

declare(strict_types=1);

namespace Bamise\Application\Response;

use Bamise\Application\DTO\ResponseEnvelope;
use Bamise\Contract\Enum\ResponseMode;
use Bamise\Contract\ValueObject\CrudResult;

final class ResponseMapper
{
    public function map(CrudResult $result, ResponseMode $mode): ResponseEnvelope
    {
        unset($mode);

        $httpStatus = $result->success ? 200 : 422;

        return new ResponseEnvelope(
            success: $result->success,
            data: $result->data,
            errors: $result->errors,
            meta: $result->meta,
            httpStatus: $httpStatus,
        );
    }
}
