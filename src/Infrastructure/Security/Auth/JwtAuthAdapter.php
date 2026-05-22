<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\Auth;

use Bamise\Application\Context\AuthSubjectDto;
use Bamise\Contract\AuthPortInterface;
use Bamise\Contract\Http\CrudRequestInterface;

/**
 * Optional JWT adapter. Install firebase/php-jwt (composer suggest) for production JWT validation.
 * Without the library, authenticate() always returns null (fail-closed).
 */
final class JwtAuthAdapter implements AuthPortInterface
{
    public function __construct(
        private readonly string $secret,
        private readonly string $subjectClaim = 'sub',
    ) {
    }

    public function subject(): ?object
    {
        return null;
    }

    public function authenticate(CrudRequestInterface $request): ?object
    {
        if (! class_exists(\Firebase\JWT\JWT::class) || ! class_exists(\Firebase\JWT\Key::class)) {
            return null;
        }

        $token = $this->extractBearerToken($request);
        if ($token === null) {
            return null;
        }

        try {
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($this->secret, 'HS256'));
        } catch (\Throwable) {
            return null;
        }

        $payload = (array) $decoded;
        $subjectId = $payload[$this->subjectClaim] ?? null;
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

    private function extractBearerToken(CrudRequestInterface $request): ?string
    {
        foreach ($request->headers() as $name => $value) {
            if (strtolower((string) $name) !== 'authorization') {
                continue;
            }

            $header = is_array($value) ? ($value[0] ?? '') : (string) $value;
            if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
