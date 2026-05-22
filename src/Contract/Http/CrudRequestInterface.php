<?php

declare(strict_types=1);

namespace Bamise\Contract\Http;

interface CrudRequestInterface
{
    public function method(): string;

    public function path(): string;

    /**
     * @return array<string, mixed>
     */
    public function input(): array;

    /**
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * @return array<string, list<string>|string>
     */
    public function headers(): array;

    public function clientIp(): ?string;
}
