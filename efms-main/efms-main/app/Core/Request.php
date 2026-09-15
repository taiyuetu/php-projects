<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Request
 *
 * Wraps PHP superglobals so controllers never touch $_GET/$_POST
 * directly. Makes controllers testable (you can build a Request by
 * hand in a unit test).
 */
final class Request
{
    private array $query;
    private array $body;
    private array $server;
    private array $files;
    private array $cookies;
    private array $params = []; // route params, set by the Router

    public function __construct(
        array $query = [],
        array $body = [],
        array $server = [],
        array $files = [],
        array $cookies = []
    ) {
        $this->query   = $query;
        $this->body    = $body;
        $this->server  = $server;
        $this->files   = $files;
        $this->cookies = $cookies;
    }

    public static function capture(): self
    {
        $body = $_POST;

        // Support JSON payloads (application/json) for API-style requests.
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = array_merge($body, $decoded);
            }
        }

        // Support PUT/PATCH/DELETE via _method override on POST forms.
        if (isset($body['_method']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $_SERVER['REQUEST_METHOD'] = strtoupper($body['_method']);
        }

        return new self($_GET, $body, $_SERVER, $_FILES, $_COOKIE);
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        return rtrim($path, '/') ?: '/';
    }

    public function isJson(): bool
    {
        return str_contains($this->server['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_contains($this->server['CONTENT_TYPE'] ?? '', 'application/json');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function header(string $key): ?string
    {
        $key = 'HTTP_' . str_replace('-', '_', strtoupper($key));

        return $this->server[$key] ?? null;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function params(): array
    {
        return $this->params;
    }
}
