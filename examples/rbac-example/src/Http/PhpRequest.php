<?php

declare(strict_types=1);

namespace App\Http;

use Bamise\Contract\Http\CrudRequestInterface;

final class PhpRequest implements CrudRequestInterface
{
    private string $method;
    /** @var array<string, mixed> */
    private array $input;
    /** @var array<string, list<string>|string> */
    private array $headers;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (in_array($this->method, ['PUT', 'PATCH'], true)) {
            parse_str(file_get_contents('php://input') ?: '', $parsed);
            $this->input = $parsed;
        } elseif ($this->method === 'POST') {
            $this->input = $_POST;
        } else {
            $this->input = $_GET;
        }

        $this->headers = $this->parseHeaders();
    }

    public function method(): string { return $this->method; }

    public function path(): string
    {
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) ? $path : '/';
    }

    public function input(): array   { return $this->input; }
    public function all(): array     { return $this->input; }
    public function headers(): array { return $this->headers; }

    public function clientIp(): ?string { return $_SERVER['REMOTE_ADDR'] ?? null; }

    /** @return array<string, list<string>|string> */
    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name           = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = is_string($value) ? $value : (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        return $headers;
    }
}
