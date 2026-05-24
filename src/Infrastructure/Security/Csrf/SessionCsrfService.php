<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\Csrf;

use Bamise\Contract\CachePortInterface;
use Bamise\Contract\Http\CrudRequestInterface;
use Bamise\Contract\Security\CsrfPortInterface;

final class SessionCsrfService implements CsrfPortInterface
{
    private const string CACHE_PREFIX = 'csrf:';

    public function __construct(
        private readonly CachePortInterface $cache,
        private readonly CsrfTokenGenerator $tokenGenerator,
        private readonly CsrfConfig $config,
    ) {
    }

    #[\Override]
    public function validate(CrudRequestInterface $request): bool
    {
        $sessionId = $this->resolveSessionId($request);
        $token = $this->extractToken($request);

        if ($sessionId === null || $token === null) {
            return false;
        }

        return $this->verify($token, $sessionId);
    }

    #[\Override]
    public function generateToken(): string
    {
        return $this->generateForSession($this->config->defaultSessionId);
    }

    public function generateForSession(string $sessionId): string
    {
        $token = $this->tokenGenerator->generate($this->config->tokenLength);
        $this->cache->set(
            $this->cacheKey($sessionId),
            $token,
            $this->config->ttlSeconds,
        );

        return $token;
    }

    public function verify(string $token, string $sessionId): bool
    {
        $key = $this->cacheKey($sessionId);
        $stored = $this->cache->get($key);

        if (! is_string($stored) || $stored === '') {
            return false;
        }

        if (! hash_equals($stored, $token)) {
            return false;
        }

        $this->cache->delete($key);

        return true;
    }

    private function cacheKey(string $sessionId): string
    {
        return self::CACHE_PREFIX . $sessionId;
    }

    private function resolveSessionId(CrudRequestInterface $request): ?string
    {
        $input = $request->input();
        $sessionId = $input[$this->config->sessionField] ?? null;

        if (is_string($sessionId) && $sessionId !== '') {
            return $sessionId;
        }

        foreach ($request->headers() as $name => $value) {
            if (strtolower((string) $name) !== 'x-session-id') {
                continue;
            }

            if (is_array($value)) {
                $candidate = $value[0] ?? null;
            } else {
                $candidate = $value;
            }

            return is_string($candidate) && $candidate !== '' ? $candidate : null;
        }

        return null;
    }

    private function extractToken(CrudRequestInterface $request): ?string
    {
        $token = $request->input()[$this->config->fieldName] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }
}
