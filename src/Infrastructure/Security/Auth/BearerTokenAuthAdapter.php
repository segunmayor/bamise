<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\Auth;

use Bamise\Application\Context\AuthSubjectDto;
use Bamise\Contract\AuthPortInterface;
use Bamise\Contract\Http\CrudRequestInterface;

/**
 * Lightweight dev/test adapter: Authorization: Bearer {subjectId}
 * Optional roles/permissions via Bearer {id}|role1,role2|perm1,perm2
 */
final class BearerTokenAuthAdapter implements AuthPortInterface
{
    private ?object $resolvedSubject = null;

    public function subject(): ?object
    {
        return $this->resolvedSubject;
    }

    public function authenticate(CrudRequestInterface $request): ?object
    {
        $this->resolvedSubject = null;

        $header = $this->authorizationHeader($request);
        if ($header === null) {
            return null;
        }

        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        $payload = trim($matches[1]);
        if ($payload === '') {
            return null;
        }

        $parts = explode('|', $payload);
        $id = $parts[0];
        $roles = isset($parts[1]) && $parts[1] !== '' ? explode(',', $parts[1]) : [];
        $permissions = isset($parts[2]) && $parts[2] !== '' ? explode(',', $parts[2]) : [];

        if (! is_numeric($id)) {
            $subjectId = $id;
        } else {
            $subjectId = str_contains($id, '.') ? $id : (int) $id;
        }

        $this->resolvedSubject = new AuthSubjectDto($subjectId, $roles, $permissions);

        return $this->resolvedSubject;
    }

    private function authorizationHeader(CrudRequestInterface $request): ?string
    {
        foreach ($request->headers() as $name => $value) {
            if (strtolower((string) $name) !== 'authorization') {
                continue;
            }

            if (is_array($value)) {
                $candidate = $value[0] ?? null;
            } else {
                $candidate = $value;
            }

            return is_string($candidate) ? $candidate : null;
        }

        return null;
    }
}
