<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\Signing;

use Bamise\Contract\CachePortInterface;
use Bamise\Contract\Http\CrudRequestInterface;
use Bamise\Contract\Security\RequestSignerPortInterface;

final class HmacRequestSigner implements RequestSignerPortInterface
{
    private const string NONCE_PREFIX = 'sign:nonce:';

    public function __construct(
        private readonly CachePortInterface $cache,
        private readonly SigningConfig $config,
    ) {
    }

    public function verify(CrudRequestInterface $request): bool
    {
        if ($this->config->secret === '') {
            return false;
        }

        $timestamp = $this->headerValue($request, $this->config->timestampHeader);
        $nonce = $this->headerValue($request, $this->config->nonceHeader);
        $signature = $this->headerValue($request, $this->config->signatureHeader);

        if ($timestamp === null || $nonce === null || $signature === null) {
            return false;
        }

        if (! ctype_digit($timestamp)) {
            return false;
        }

        $timestampInt = (int) $timestamp;
        $now = time();
        if (abs($now - $timestampInt) > $this->config->maxSkewSeconds) {
            return false;
        }

        $nonceKey = self::NONCE_PREFIX . $nonce;
        if ($this->cache->get($nonceKey) !== null) {
            return false;
        }

        $expected = $this->computeSignature(
            $request->method(),
            $request->path(),
            $timestamp,
            $nonce,
            $this->bodyHash($request),
        );

        if (! hash_equals($expected, $signature)) {
            return false;
        }

        $this->cache->set($nonceKey, true, $this->config->nonceTtlSeconds);

        return true;
    }

    /**
     * @param array<string, mixed> $payload Keys: method, path, timestamp, nonce, body (array) or body_hash
     */
    public function sign(array $payload): string
    {
        $method = (string) ($payload['method'] ?? 'GET');
        $path = (string) ($payload['path'] ?? '/');
        $timestamp = (string) ($payload['timestamp'] ?? (string) time());
        $nonce = (string) ($payload['nonce'] ?? bin2hex(random_bytes(16)));

        if (isset($payload['body_hash']) && is_string($payload['body_hash'])) {
            $bodyHash = $payload['body_hash'];
        } else {
            /** @var array<string, mixed> $body */
            $body = is_array($payload['body'] ?? null) ? $payload['body'] : [];
            $bodyHash = $this->hashBody($body);
        }

        return $this->computeSignature($method, $path, $timestamp, $nonce, $bodyHash);
    }

    private function computeSignature(
        string $method,
        string $path,
        string $timestamp,
        string $nonce,
        string $bodyHash,
    ): string {
        $canonical = implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $nonce,
            $bodyHash,
        ]);

        return hash_hmac('sha256', $canonical, $this->config->secret);
    }

    private function bodyHash(CrudRequestInterface $request): string
    {
        return $this->hashBody($request->input());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function hashBody(array $body): string
    {
        $encoded = json_encode($body, JSON_THROW_ON_ERROR);

        return hash('sha256', $encoded);
    }

    private function headerValue(CrudRequestInterface $request, string $name): ?string
    {
        $normalized = strtolower($name);

        foreach ($request->headers() as $key => $value) {
            if (strtolower((string) $key) !== $normalized) {
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
}
