<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\Auth;

use Bamise\Application\Context\AuthSubjectDto;
use Bamise\Contract\AuthPortInterface;
use Bamise\Contract\Http\CrudRequestInterface;

/**
 * Form/session test adapter: resolves subject id from request input session field.
 */
final class SessionAuthAdapter implements AuthPortInterface
{
    public function __construct(
        private readonly string $subjectField = '_subject_id',
    ) {
    }

    public function subject(): ?object
    {
        return null;
    }

    public function authenticate(CrudRequestInterface $request): ?object
    {
        $subjectId = $request->input()[$this->subjectField] ?? null;
        if ($subjectId === null || $subjectId === '') {
            return null;
        }

        if (is_int($subjectId) || (is_string($subjectId) && ctype_digit($subjectId))) {
            return new AuthSubjectDto((int) $subjectId);
        }

        if (! is_string($subjectId)) {
            return null;
        }

        return new AuthSubjectDto($subjectId);
    }
}
