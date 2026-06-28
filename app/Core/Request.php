<?php

namespace App\Core;

final class Request
{
    public function __construct(private string $method, private string $path, private array $input, private array $query, private array $headers)
    {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $script = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script !== '/' && $script !== '\\' && str_starts_with($path, $script)) {
            $path = substr($path, strlen($script)) ?: '/';
        }
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        $input = is_array($json) ? $json : $_POST;
        if (isset($input['_method'])) {
            $method = strtoupper((string) $input['_method']);
        }
        return new self($method, '/' . trim($path, '/'), $input, $_GET, self::headers());
    }

    private static function headers(): array
    {
        if (function_exists('getallheaders')) {
            return array_change_key_case(getallheaders(), CASE_LOWER);
        }
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }
        return $headers;
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }
    public function input(?string $key = null, mixed $default = null): mixed { return $key === null ? $this->input : ($this->input[$key] ?? $default); }
    public function query(?string $key = null, mixed $default = null): mixed { return $key === null ? $this->query : ($this->query[$key] ?? $default); }
    public function header(string $key, mixed $default = null): mixed { return $this->headers[strtolower($key)] ?? $default; }
    public function bearerToken(): ?string
    {
        $authorization = (string) $this->header('authorization', '');
        if (preg_match('/Bearer\s+(.+)/i', $authorization, $matches)) {
            return trim($matches[1]);
        }
        return $this->header('x-auth-token') ?: null;
    }
}
