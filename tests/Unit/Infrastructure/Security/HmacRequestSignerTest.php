<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Infrastructure\Cache\InMemoryCache;
use Bamise\Infrastructure\Security\Signing\HmacRequestSigner;
use Bamise\Infrastructure\Security\Signing\SigningConfig;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

final class HmacRequestSignerTest extends TestCase
{
    private const string SECRET = 'test-signing-secret';

    public function test_valid_signature_is_accepted(): void
    {
        $signer = $this->signer();
        $body = ['name' => 'Ada'];
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(8));
        $signature = $signer->sign([
            'method' => 'POST',
            'path' => '/api/users',
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'body' => $body,
        ]);

        $request = new FakeCrudRequest(
            'POST',
            '/api/users',
            $body,
            [
                'X-Bamise-Timestamp' => $timestamp,
                'X-Bamise-Nonce' => $nonce,
                'X-Bamise-Signature' => $signature,
            ],
        );

        self::assertTrue($signer->verify($request));
    }

    public function test_replay_nonce_is_rejected(): void
    {
        $signer = $this->signer();
        $body = [];
        $timestamp = (string) time();
        $nonce = 'fixed-nonce';
        $signature = $signer->sign([
            'method' => 'GET',
            'path' => '/',
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'body' => $body,
        ]);

        $request = new FakeCrudRequest(
            'GET',
            '/',
            $body,
            [
                'X-Bamise-Timestamp' => $timestamp,
                'X-Bamise-Nonce' => $nonce,
                'X-Bamise-Signature' => $signature,
            ],
        );

        self::assertTrue($signer->verify($request));
        self::assertFalse($signer->verify($request));
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $signer = $this->signer();
        $timestamp = (string) (time() - 400);
        $nonce = bin2hex(random_bytes(8));
        $signature = $signer->sign([
            'method' => 'GET',
            'path' => '/',
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'body' => [],
        ]);

        $request = new FakeCrudRequest(
            'GET',
            '/',
            [],
            [
                'X-Bamise-Timestamp' => $timestamp,
                'X-Bamise-Nonce' => $nonce,
                'X-Bamise-Signature' => $signature,
            ],
        );

        self::assertFalse($signer->verify($request));
    }

    public function test_empty_secret_rejects_all_requests(): void
    {
        $signer = new HmacRequestSigner(new InMemoryCache(), new SigningConfig(secret: ''));

        $request = new FakeCrudRequest(
            'GET',
            '/',
            [],
            [
                'X-Bamise-Timestamp' => (string) time(),
                'X-Bamise-Nonce' => 'any-nonce',
                'X-Bamise-Signature' => 'any-signature',
            ],
        );

        self::assertFalse($signer->verify($request));
    }

    private function signer(): HmacRequestSigner
    {
        return new HmacRequestSigner(
            new InMemoryCache(),
            new SigningConfig(secret: self::SECRET, maxSkewSeconds: 300),
        );
    }
}
