<?php

declare(strict_types=1);

namespace Bamise\Tests\Fixtures;

use Bamise\Contract\Http\CrudRequestInterface;

final class FakeCrudRequest implements CrudRequestInterface
{
    /**
     * @param array<string, mixed>                    $input
     * @param array<string, list<string>|string>      $headers
     */
    public function __construct(
        private string $method = 'GET',
        private string $path = '/',
        private array $input = [],
        private array $headers = [],
        private ?string $clientIp = null,
    ) {
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function input(): array
    {
        return $this->input;
    }

    public function all(): array
    {
        return $this->input;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function clientIp(): ?string
    {
        return $this->clientIp;
    }

    public function withMethod(string $method): self
    {
        $clone = clone $this;
        $clone->method = $method;

        return $clone;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function withInput(array $input): self
    {
        $clone = clone $this;
        $clone->input = $input;

        return $clone;
    }

    /**
     * @param array<string, list<string>|string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->headers = $headers;

        return $clone;
    }
}
